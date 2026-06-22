<?php
use Livewire\Component;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\Auth;
use App\Models\Anggota; 
use App\Models\Simpanan; 
use Livewire\WithFileUploads; 

new class extends Component {
    use WithFileUploads; 

    public $user;
    public $isLengkap = true;
    
    public $nik;
    public $telepon;
    public $alamat;
    public $foto; 

    public function mount()
    {
        $this->user = Auth::user();

        // Gembok Keamanan
        if (!$this->user || $this->user->peran !== 'anggota') {
            return redirect()->to('/login');
        }

        

        // Cek Kelengkapan lewat relasi
        if (!$this->user->anggota) {
            $this->isLengkap = false;
        } else {
            $this->isLengkap = true;
        }
    }

    // Mengambil data profil secara otomatis lewat relasi
    #[Computed]
    public function profilAnggota()
    {
        return $this->user->anggota;
    }

    // Fungsi simpan kelengkapan data diri dari sisi Anggota
    public function simpanDataDiri()
    {
        $this->validate([
            'nik' => 'required|numeric|digits:16|unique:anggota,nik',
            'telepon' => 'required|numeric|digits_between:10,15',
            'alamat' => 'required|string|min:10',
            'foto' => 'required|image|max:2048', 
        ]);

        $pathFoto = $this->foto->store('foto-ktp', 'public');

        // Generate Nomor Anggota otomatis sementara
        $totalAnggota = Anggota::count() + 1;
        $nomorAnggota = 'KOP-' . date('Y') . '-' . str_pad($totalAnggota, 4, '0', STR_PAD_LEFT);

        $anggotaBaru = Anggota::create([
            'user_id'       => $this->user->id,
            'nomor_anggota' => $nomorAnggota,
            'nama'          => $this->user->nama,
            'nik'           => $this->nik,
            'telepon'       => $this->telepon,
            'alamat'        => $this->alamat,
            'foto_ktp'      => $pathFoto, 
            'status'        => 'nonaktif' // Default mendaftar adalah nonaktif
        ]);

        Simpanan::create([
            'anggota_id'           => $anggotaBaru->id,
            'kategori_simpanan_id' => 1, 
            'jenis_transaksi'      => 'setor',
            'jumlah'               => 0, 
            'tanggal_transaksi'    => date('Y-m-d'),
        ]);

        $this->user->load('anggota'); 
        $this->isLengkap = true;
        
        session()->flash('sukses_data', 'Profil Berhasil Dikirim! Berkas Anda kini dalam antrean peninjauan admin.');
        return redirect()->to(request()->header('Referer'));
    }

    #[Computed]
    public function totalSaldo()
    {
        if (!$this->profilAnggota) {
            return 0;
        }

        $totalSetor = $this->profilAnggota->simpanan()->where('jenis_transaksi', 'setor')->sum('jumlah');
        $totalTarik = $this->profilAnggota->simpanan()->where('jenis_transaksi', 'tarik')->sum('jumlah');

        return $totalSetor - $totalTarik;
    }

    public function logout()
    {
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();
        return redirect()->to('/login');
    }
};
?>
<div class="min-h-screen bg-slate-50 font-sans antialiased text-slate-800">
    <nav class="bg-white/80 backdrop-blur-md sticky top-0 z-50 border-b border-slate-200/80 px-6 py-4 flex justify-between items-center shadow-sm">
        <div class="flex items-center space-x-3">
            <div class="bg-gradient-to-tr from-blue-600 to-indigo-600 p-2 rounded-xl text-white shadow-md shadow-blue-500/20">
                <span class="text-xl font-bold tracking-wider">⚡</span>
            </div>
            <span class="text-lg font-black tracking-tight bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent">KOP-ID MEMBER</span>
        </div>
        <button wire:click="logout" class="bg-rose-50 text-rose-600 px-4 py-2 rounded-xl font-bold text-sm hover:bg-rose-100/80 transition-all duration-200 border border-rose-100 active:scale-95 flex items-center space-x-1">
            <span>Keluar</span>
            <span>➔</span>
        </button>
    </nav>

    <div class="max-w-4xl mx-auto p-6 space-y-8">
        
        @if(!$isLengkap)
            <div class="bg-white rounded-3xl shadow-xl shadow-slate-200/50 border border-slate-100 p-8 max-w-xl mx-auto mt-6 transition-all duration-300">
                <div class="text-center mb-8">
                    <div class="w-16 h-16 bg-amber-50 rounded-2xl flex items-center justify-center mx-auto border border-amber-100 shadow-sm mb-3 text-2xl">
                        ⚠️
                    </div>
                    <h3 class="text-2xl font-black text-slate-800 tracking-tight">Lengkapi Profil Anggota</h3>
                    <p class="text-sm text-slate-400 mt-1.5 max-w-sm mx-auto">Halo <span class="font-semibold text-slate-700">{{ $user->nama }}</span>, mohon lengkapi data identitas Anda untuk mengaktifkan seluruh fitur koperasi.</p>
                </div>

                <form wire:submit="simpanDataDiri" class="space-y-5">
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Nomor Induk Kependudukan (NIK KTP)</label>
                        <input type="text" wire:model="nik" placeholder="16 digit nomor KTP asli" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 text-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all text-sm shadow-sm">
                        @error('nik') <span class="text-rose-500 text-xs mt-1 block font-medium">⚠️ {{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Nomor Telepon / WhatsApp</label>
                        <input type="text" wire:model="telepon" placeholder="Contoh: 08123456789" class="w-full px-4 py-3 bg-slate-50 border border-slate-200 text-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all text-sm shadow-sm">
                        @error('telepon') <span class="text-rose-500 text-xs mt-1 block font-medium">⚠️ {{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Alamat Rumah Lengkap</label>
                        <textarea wire:model="alamat" rows="3" placeholder="Tuliskan alamat lengkap tempat tinggal sekarang..." class="w-full px-4 py-3 bg-slate-50 border border-slate-200 text-slate-700 rounded-xl focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all text-sm shadow-sm resize-none"></textarea>
                        @error('alamat') <span class="text-rose-500 text-xs mt-1 block font-medium">⚠️ {{ $message }}</span> @enderror
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Unggah Foto KTP</label>
                        <div class="flex items-center justify-center w-full">
                            <label class="flex flex-col items-center justify-center w-full h-36 border-2 border-slate-200 border-dashed rounded-2xl cursor-pointer bg-slate-50 hover:bg-slate-100/70 transition-all overflow-hidden relative shadow-sm">
                                @if ($foto)
                                    <img src="{{ $foto->temporaryUrl() }}" class="absolute inset-0 w-full h-full object-cover">
                                @else
                                    <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                        <span class="text-2xl mb-1">📸</span>
                                        <p class="text-xs text-slate-500 font-bold">Klik untuk pilih foto KTP</p>
                                        <p class="text-[10px] text-slate-400 mt-1">Format: JPG, PNG (Maks. 2MB)</p>
                                    </div>
                                @endif
                                <input type="file" wire:model="foto" class="hidden" accept="image/*" />
                            </label>
                        </div>
                        <div wire:loading wire:target="foto" class="text-xs text-blue-500 font-bold mt-1.5 animate-pulse">🔄 Sedang memproses gambar...</div>
                        @error('foto') <span class="text-rose-500 text-xs mt-1 block font-medium">⚠️ {{ $message }}</span> @enderror
                    </div>

                    <button type="submit" class="w-full mt-2 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 text-white py-3.5 rounded-xl font-bold text-sm tracking-wide shadow-lg shadow-blue-500/20 transition-all duration-150 transform active:scale-[0.99]">
                        Simpan & Buka Dashboard
                    </button>
                </form>
            </div>

        @elseif($this->profilAnggota && $this->profilAnggota->status === 'nonaktif')
            <div class="max-w-xl mx-auto bg-white border border-slate-200/70 shadow-xl shadow-slate-200/40 rounded-3xl p-8 text-center space-y-6 mt-6 animate-fade-in">
                <div class="relative w-20 h-20 mx-auto">
                    <div class="absolute inset-0 bg-amber-400/20 rounded-full animate-ping"></div>
                    <div class="relative w-20 h-20 bg-amber-50 rounded-full border border-amber-200 flex items-center justify-center text-3xl">
                        ⏳
                    </div>
                </div>

                <div class="space-y-2">
                    <h2 class="text-2xl font-black text-slate-800 tracking-tight">Akun Menunggu Verifikasi</h2>
                    <p class="text-sm text-slate-400 max-w-sm mx-auto leading-relaxed">
                        Hai <span class="font-bold text-slate-700">{{ $user->nama }}</span>, berkas identitas fisik Anda berhasil kami terima dan saat ini sedang ditinjau manual oleh Petugas Koperasi.
                    </p>
                </div>

                <div class="bg-amber-50/60 border border-amber-100 rounded-2xl p-4 text-left text-xs font-medium text-amber-800 space-y-1">
                    <p class="font-bold text-amber-900 flex items-center gap-1">💡 Informasi Penting:</p>
                    <p class="text-slate-500 leading-normal">Selama masa peninjauan berkas, fitur transaksi keuangan, penarikan dana, dan kartu keanggotaan digital Anda dibekukan sementara hingga disetujui resmi.</p>
                </div>

                <div class="border border-slate-100 rounded-2xl bg-slate-50/50 p-4 text-left space-y-2.5 text-xs">
                    <p class="font-bold text-slate-400 uppercase tracking-wider text-[10px] border-b border-slate-100 pb-1.5">Arsip Berkas Masuk:</p>
                    <div class="flex justify-between"><span class="text-slate-400 font-medium">Nomor NIK KTP:</span> <span class="font-mono font-bold text-slate-700">{{ $this->profilAnggota->nik }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-400 font-medium">No. Handphone:</span> <span class="font-semibold text-slate-700">{{ $this->profilAnggota->telepon }}</span></div>
                    <div class="flex justify-between"><span class="text-slate-400 font-medium">Waktu Daftar:</span> <span class="font-medium text-slate-500">{{ $this->profilAnggota->created_at->format('d M Y - H:i') }} WIB</span></div>
                </div>

                <div class="pt-2">
                    <button wire:click="$refresh" class="w-full bg-slate-100 hover:bg-slate-200 text-slate-700 py-2.5 rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-1.5 active:scale-95 shadow-sm">
                        🔄 Cek Ulang Status Terkini
                    </button>
                </div>
            </div>

        @else
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 animate-fade-in">
                <div>
                    <h2 class="text-2xl font-black text-slate-800 tracking-tight">Selamat Datang, {{ $user->nama }}!</h2>
                    <p class="text-sm text-slate-400 mt-1">Seluruh layanan finansial mandiri Anda aktif dan terpantau aman.</p>
                </div>
                
                @if (session()->has('sukses_data'))
                    <div class="bg-emerald-50 border border-emerald-100 text-emerald-700 px-4 py-2.5 rounded-xl text-xs font-bold shadow-sm animate-bounce flex items-center space-x-1.5">
                        <span>🎉</span> <span>{{ session('sukses_data') }}</span>
                    </div>
                @endif
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 text-white p-6 rounded-3xl shadow-xl shadow-indigo-950/10 relative overflow-hidden flex flex-col justify-between h-52 border border-white/5 group hover:shadow-indigo-500/5 transition-all duration-300">
                    <div class="absolute -right-10 -bottom-10 w-44 h-44 bg-blue-500/10 rounded-full blur-2xl group-hover:bg-blue-500/20 transition-all duration-500"></div>
                    <div class="absolute right-6 top-6 opacity-10 text-6xl">🏛️</div>
                    
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-[10px] text-slate-400 uppercase tracking-widest font-black">Kartu Digital Anggota</p>
                            <p class="text-xl font-mono font-bold tracking-widest mt-1.5 text-blue-400">{{ $this->profilAnggota?->nomor_anggota }}</p>
                        </div>
                        <div class="bg-white/10 px-3 py-1.5 rounded-xl backdrop-blur-md border border-white/10 text-xs font-bold text-slate-200 tracking-wider">
                            MEMBER
                        </div>
                    </div>
                    
                    <div class="border-t border-white/5 pt-4 flex justify-between items-end">
                        <div>
                            <p class="text-[9px] text-slate-500 uppercase tracking-widest font-bold">Nama Pemegang</p>
                            <p class="text-lg font-bold uppercase tracking-wide truncate max-w-[180px] mt-0.5">{{ $user->nama }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-[9px] text-slate-500 uppercase tracking-widest font-bold">Status Akun</p>
                            <p class="text-xs font-black text-emerald-400 uppercase tracking-wider flex items-center justify-end space-x-1 mt-0.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                <span>{{ $this->profilAnggota?->status }}</span>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-3xl shadow-sm border border-slate-200/60 flex flex-col justify-between h-52 relative group">
                    <div>
                        <div class="flex justify-between items-center">
                            <p class="text-slate-400 text-xs font-bold uppercase tracking-wider">Total Saldo Tabungan Anda</p>
                            <span class="text-slate-300 text-xl group-hover:text-emerald-500 transition-colors">💰</span>
                        </div>
                        <p class="text-4xl font-black text-slate-800 tracking-tight mt-3">
                            <span class="text-2xl font-bold text-slate-400">Rp</span> {{ number_format($this->totalSaldo, 0, ',', '.') }}
                        </p>
                    </div>
                    
                    <div class="bg-slate-50 rounded-2xl p-3 border border-slate-100 flex justify-between items-center text-xs text-slate-400">
                        <span>Pembaruan terakhir</span>
                        <span class="font-semibold text-slate-500">Hari ini, {{ date('H:i') }} WIB</span>
                    </div>
                </div>
            </div>

            <div class="space-y-3">
                <h3 class="text-sm font-bold text-slate-400 uppercase tracking-wider">Aksi Cepat</h3>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <button class="bg-white p-4 rounded-2xl border border-slate-200/60 shadow-sm flex flex-col items-center justify-center text-center space-y-2 hover:border-blue-500 hover:shadow-md hover:shadow-blue-500/5 transition-all duration-200 group">
                        <div class="w-10 h-10 bg-blue-50 rounded-xl text-blue-600 flex items-center justify-center font-bold text-lg group-hover:bg-blue-600 group-hover:text-white transition-all">📥</div>
                        <span class="text-xs font-bold text-slate-600">Setor Tunai</span>
                    </button>
                    <button class="bg-white p-4 rounded-2xl border border-slate-200/60 shadow-sm flex flex-col items-center justify-center text-center space-y-2 hover:border-indigo-500 hover:shadow-md hover:shadow-indigo-500/5 transition-all duration-200 group">
                        <div class="w-10 h-10 bg-indigo-50 rounded-xl text-indigo-600 flex items-center justify-center font-bold text-lg group-hover:bg-indigo-600 group-hover:text-white transition-all">📤</div>
                        <span class="text-xs font-bold text-slate-600">Tarik Saldo</span>
                    </button>
                    <button class="bg-white p-4 rounded-2xl border border-slate-200/60 shadow-sm flex flex-col items-center justify-center text-center space-y-2 hover:border-purple-500 hover:shadow-md hover:shadow-purple-500/5 transition-all duration-200 group">
                        <div class="w-10 h-10 bg-purple-50 rounded-xl text-purple-600 flex items-center justify-center font-bold text-lg group-hover:bg-purple-600 group-hover:text-white transition-all">🧾</div>
                        <span class="text-xs font-bold text-slate-600">Pinjaman Baru</span>
                    </button>
                    <button class="bg-white p-4 rounded-2xl border border-slate-200/60 shadow-sm flex flex-col items-center justify-center text-center space-y-2 hover:border-slate-400 hover:shadow-md transition-all duration-200 group">
                        <div class="w-10 h-10 bg-slate-50 rounded-xl text-slate-600 flex items-center justify-center font-bold text-lg group-hover:bg-slate-700 group-hover:text-white transition-all">⚙️</div>
                        <span class="text-xs font-bold text-slate-600">Pengaturan</span>
                    </button>
                </div>
            </div>

            <div class="bg-white rounded-3xl border border-slate-200/60 p-6 shadow-sm space-y-4">
                <div class="flex justify-between items-center border-b border-slate-100 pb-3">
                    <h3 class="font-black text-slate-800 tracking-tight">Aktivitas Rekening Terbaru</h3>
                    <a href="#" class="text-xs font-bold text-blue-600 hover:underline">Lihat Semua</a>
                </div>
                
                <div class="text-center py-8 text-slate-400 flex flex-col items-center justify-center space-y-2">
                    <span class="text-3xl opacity-60">🍃</span>
                    <p class="text-xs font-semibold">Belum ada riwayat transaksi keuangan</p>
                    <p class="text-[11px] text-slate-300">Setoran awal Anda akan otomatis muncul di sini setelah diverifikasi petugas.</p>
                </div>
            </div>
        @endif

    </div>
</div>