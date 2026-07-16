@php
$paginator = new \Illuminate\Pagination\LengthAwarePaginator(collect(range(1, 20)), 200, 20, 3);
@endphp

<atom:pagination :paginator="$paginator" :summary="false"/>
