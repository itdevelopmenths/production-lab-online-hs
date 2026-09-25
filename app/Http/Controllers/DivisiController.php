<?php

namespace App\Http\Controllers;

use App\Http\Requests\DivisiStoreRequest;
use App\Http\Requests\DivisiUpdateRequest;
use App\Models\Divisi;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Yajra\DataTables\Facades\DataTables;

class DivisiController extends Controller
{
    public function index(): View
    {
        $this->authorize('divisi.view');

        return view('divisi.index');
    }

    public function data(): JsonResponse
    {
        $this->authorize('divisi.view');

        // Cek izin sekali per request, bukan per baris
        $canEdit = auth()->user()->can('divisi.edit');
        $canDelete = auth()->user()->can('divisi.delete');

        $query = Divisi::query()->withCount('users');

        return DataTables::eloquent($query)
            ->addColumn('kode_badge', function (Divisi $d) {
                return '<span class="font-mono font-medium text-slate-700 bg-slate-100 px-2 py-0.5 rounded-sm border border-slate-200 text-xs">'
                    . e($d->kode) . '</span>';
            })
            ->addColumn('nama_section', function (Divisi $d) {
                $nama = e($d->nama);
                $desc = $d->deskripsi ? '<p class="text-xs text-slate-500 mt-0.5 line-clamp-1">' . e($d->deskripsi) . '</p>' : '';
                return '<div>
                            <div class="font-semibold text-slate-900 text-sm leading-snug">' . $nama . '</div>
                            ' . $desc . '
                        </div>';
            })
            ->addColumn('badge_preview', function (Divisi $d) {
                return '<span class="inline-flex items-center px-2.5 py-0.5 rounded text-xs font-semibold ' . $d->badgeClass() . ' border">'
                    . e($d->nama) . '</span>';
            })
            ->addColumn('users_count_badge', function (Divisi $d) {
                $count = $d->users_count;
                $cls = $count > 0 
                    ? 'bg-primary-50 text-primary-800 border-primary-200/80 font-semibold' 
                    : 'bg-slate-50 text-slate-500 border-slate-200/80';
                
                $url = route('users.index', ['divisi' => $d->kode]);
                return '<a href="' . $url . '" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs border ' . $cls . ' hover:opacity-80 transition" title="Lihat staf divisi ini">
                            <svg class="w-3 h-3 mr-1 opacity-70" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-1.13a4 4 0 10-4-4 4 4 0 004 4z"/></svg>
                            ' . $count . ' Staf
                        </a>';
            })
            ->addColumn('status_badge', function (Divisi $d) {
                if ($d->is_active) {
                    return '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200/80">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 mr-1.5"></span>
                                Aktif
                            </span>';
                }

                return '<span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-slate-100 text-slate-600 border border-slate-200">
                            <span class="w-1.5 h-1.5 rounded-full bg-slate-400 mr-1.5"></span>
                            Nonaktif
                        </span>';
            })
            ->addColumn('action', function (Divisi $d) use ($canEdit, $canDelete) {
                $html = '<div class="flex items-center justify-center gap-3">';
                if ($canEdit) {
                    $html .= '<a href="' . e(route('divisi.edit', $d)) . '" class="text-primary-600 hover:text-primary-800 text-xs font-semibold">Edit</a>';
                }
                if ($canDelete) {
                    $html .= '<button type="button" onclick="hapus(\'' . e(route('divisi.destroy', $d)) . '\', \'' . e(addslashes($d->nama)) . '\')" class="text-rose-500 hover:text-rose-700 text-xs font-semibold cursor-pointer">Hapus</button>';
                }

                return $html . '</div>';
            })
            ->rawColumns(['kode_badge', 'nama_section', 'badge_preview', 'users_count_badge', 'status_badge', 'action'])
            ->toJson();
    }

    public function create(): View
    {
        $this->authorize('divisi.create');

        $availableColors = Divisi::AVAILABLE_COLORS;
        $divisi = new Divisi([
            'color' => 'slate',
            'is_active' => true,
        ]);

        return view('divisi.create', compact('divisi', 'availableColors'));
    }

    public function store(DivisiStoreRequest $request): RedirectResponse
    {
        $divisi = Divisi::create($request->validated());

        return redirect()->route('divisi.index')
            ->with('success', "Divisi '{$divisi->nama}' berhasil ditambahkan ke master data.");
    }

    public function edit(Divisi $divisi): View
    {
        $this->authorize('divisi.edit');

        $availableColors = Divisi::AVAILABLE_COLORS;

        return view('divisi.edit', compact('divisi', 'availableColors'));
    }

    public function update(DivisiUpdateRequest $request, Divisi $divisi): RedirectResponse
    {
        $oldKode = $divisi->kode;
        $validated = $request->validated();
        $divisi->update($validated);

        // Jika kode diubah, sinkronkan users.divisi string untuk backward compatibility
        if ($oldKode !== $divisi->kode) {
            User::where('divisi_id', $divisi->id)->update(['divisi' => $divisi->kode]);
        }

        return redirect()->route('divisi.index')
            ->with('success', "Data divisi '{$divisi->nama}' berhasil diperbarui.");
    }

    public function destroy(Request $request, Divisi $divisi): JsonResponse|RedirectResponse
    {
        $this->authorize('divisi.delete');

        $userCount = $divisi->users()->count();
        if ($userCount > 0) {
            $msg = "Divisi '{$divisi->nama}' tidak dapat dihapus karena masih digunakan oleh {$userCount} pengguna aktif. Mohon alihkan divisi pengguna terlebih dahulu.";
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            return redirect()->back()->with('error', $msg);
        }

        $nama = $divisi->nama;
        $divisi->delete();

        $msg = "Divisi '{$nama}' berhasil dihapus dari sistem.";
        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $msg]);
        }

        return redirect()->route('divisi.index')->with('success', $msg);
    }
}
