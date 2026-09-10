<x-app-layout title="Edit Produk">
    <div class="max-w-3xl">
        <a href="{{ route('produk.index') }}" class="text-sm text-gray-500 hover:text-gray-700">&larr; Kembali</a>
        <div class="mt-4 bg-white rounded-xl border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-6">Edit Produk</h3>
            <form method="POST" action="{{ route('produk.update', $produk) }}">
                @csrf @method('PUT')
                @include('produk._form')
                <div class="mt-6 flex justify-end gap-3">
                    <a href="{{ route('produk.index') }}" class="px-4 py-2 text-sm text-gray-600 hover:text-gray-900">Batal</a>
                    <button type="submit" class="px-4 py-2 bg-primary-500 text-white text-sm font-medium rounded-xl hover:bg-primary-600">Perbarui</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
