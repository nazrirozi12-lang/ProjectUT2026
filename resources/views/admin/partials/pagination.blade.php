{{-- Paginasi admin (data nyata). Variabel: $paginator, $label (mis. "pesanan") --}}
<div class="admin-pagination-bar">
    <span class="pagination-info">
        @if ($paginator->total() === 0)
            Tidak ada {{ $label ?? 'data' }} yang cocok
        @else
            Menampilkan {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} dari {{ $paginator->total() }} {{ $label ?? 'data' }}
        @endif
    </span>
    @if ($paginator->hasPages())
        <div class="pagination-controls">
            @if ($paginator->onFirstPage())
                <span class="btn-page-nav is-disabled">Sebelumnya</span>
            @else
                <a class="btn-page-nav" href="{{ $paginator->previousPageUrl() }}">Sebelumnya</a>
            @endif
            <div class="pagination-numbers">
                @php
                    $current = $paginator->currentPage();
                    $last = $paginator->lastPage();
                    $from = max(1, $current - 2);
                    $to = min($last, $current + 2);
                @endphp
                @for ($p = $from; $p <= $to; $p++)
                    @if ($p === $current)
                        <span class="page-num is-active">● {{ $p }}</span>
                    @else
                        <a class="page-num" href="{{ $paginator->url($p) }}">{{ $p }}</a>
                    @endif
                @endfor
            </div>
            @if ($paginator->hasMorePages())
                <a class="btn-page-nav" href="{{ $paginator->nextPageUrl() }}">Berikutnya</a>
            @else
                <span class="btn-page-nav is-disabled">Berikutnya</span>
            @endif
        </div>
    @endif
</div>
