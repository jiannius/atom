{{-- hand-crumbs. A line marked `expect:` is a finding; every other line must stay clean. --}}
<nav aria-label="Breadcrumb"> {{-- expect: hand-crumbs --}}
    <ol>
        <li><a href="/jobs">Jobs</a></li>
        <li>WO-1042</li>
    </ol>
</nav>

<nav aria-label="{{ __('Breadcrumbs') }}"> {{-- expect: hand-crumbs --}}
    <span>Jobs</span>
</nav>

<nav aria-label="Breadcrumbs"> {{-- expect: hand-crumbs --}}
    <a href="/jobs">Jobs</a><atom:icon.right /><a href="/jobs/1">WO-1</a>
</nav>

<nav class="text-sm">
    <a href="/jobs">Jobs</a><atom:icon.right class="size-3" /><a href="/jobs/1">WO-1</a> {{-- expect: hand-crumbs --}}
</nav>

<nav class="text-sm">
    <atom:link href="/jobs">Jobs</atom:link> › <atom:link href="/jobs/1">WO-1</atom:link> {{-- expect: hand-crumbs --}}
</nav>

<nav class="text-sm">
    <div class="flex">
        <a href="/jobs">Jobs</a> › <a href="/jobs/1">WO-1</a> {{-- expect: hand-crumbs --}}
    </div>
</nav>

<ol>
    {{-- expect: hand-crumbs --}}
    <a href="/">Home</a>
    <atom:icon.arrow-right />
    <a href="/jobs">Jobs</a>
    <span>»</span>
    <a href="/jobs/1">WO-1</a>
</ol>

<ul class="flex">
    <a href="/jobs">Jobs</a> <span>/</span> <a href="/jobs/1">WO-1</a> <span>/</span> <a href="/jobs/1/parts">Parts</a> {{-- expect: hand-crumbs --}}
</ul>

<nav class="text-sm">
    <div>
        <a href="/jobs">Jobs</a> / <a href="/jobs/1">WO-1</a> / <a href="/jobs/1/parts">Parts</a> {{-- expect: hand-crumbs --}}
    </div>
</nav>

<ul class="flex"><a href="/jobs">Jobs</a>&raquo;<a href="/jobs/1">WO-1</a></ul> {{-- expect: hand-crumbs --}}

<ul class="flex"><a href="/jobs">Jobs</a>&nbsp;›&nbsp;<a href="/jobs/1">WO-1</a></ul> {{-- expect: hand-crumbs --}}

<ul class="flex"><a href="/jobs">Jobs</a> › <a href="/jobs/1">WO-1</a></ul> {{-- expect: hand-crumbs --}}

<ol><a href="/jobs">Jobs</a><li>›</li><a href="/jobs/1">WO-1</a></ol> {{-- expect: hand-crumbs --}}

<ol><a href="/jobs">Jobs</a><div>›</div><a href="/jobs/1">WO-1</a></ol> {{-- expect: hand-crumbs --}}

<ol><a href="/jobs">Jobs</a><i>›</i><a href="/jobs/1">WO-1</a></ol> {{-- expect: hand-crumbs --}}

<div class="flex gap-2"><a href="/">Home</a> › <a href="/jobs">Jobs</a> › <a href="/jobs/1">WO-1</a></div> {{-- expect: hand-crumbs --}}

<div class="flex"><a href="/jobs">Jobs</a><atom:icon.right /><a href="/jobs/1">WO-1</a><atom:icon.right /><a href="/jobs/1/parts">Parts</a></div> {{-- expect: hand-crumbs --}}
