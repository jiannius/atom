{{-- none of these is flagged --}}
<nav aria-label="Main menu">
    <a href="/jobs">Jobs</a>
</nav>

<nav aria-label="{{ $breadcrumbLabel }}">
    <a href="/jobs">Jobs</a>
</nav>

<nav aria-label="{{ $label }}">
    <a href="/jobs">Jobs</a>
</nav>

<nav aria-label="Breadcrumb">
    <atom:breadcrumbs :items="$trail" />
</nav>

<x-slot:crumbs>
    <atom:breadcrumbs :items="[['label' => 'Jobs', 'href' => route('jobs')], ['label' => $job->number]]" />
</x-slot:crumbs>

<footer>
    <a href="/terms">Terms</a> / <a href="/privacy">Privacy</a>
</footer>

<footer>
    <nav>
        <a href="/terms">Terms</a> / <a href="/privacy">Privacy</a> / <a href="/cookies">Cookies</a>
    </nav>
    <a href="/terms">Terms</a>›<a href="/privacy">Privacy</a>
</footer>

<p>
    <a href="/terms">Terms</a> / <a href="/privacy">Privacy</a> / <a href="/cookies">Cookies</a>
</p>

<ul class="flex">
    <a href="/terms">Terms</a> / <a href="/privacy">Privacy</a>
</ul>

<div>
    <a href="/jobs">Jobs</a><span><atom:icon.right /> and</span><a href="/parts">Parts</a>
</div>

<div>
    <a href="/jobs">Next <atom:icon.right /></a>
</div>

<div>
    <a href="/jobs">Jobs</a><atom:icon.right /><span>WO-1</span>
</div>

<div>
    <a href="/jobs">Jobs</a> | <a href="/parts">Parts</a>
</div>

<div>
    <a href="/jobs">Jobs</a><span>and</span><a href="/parts">Parts</a>
</div>

<div>
    <a href="/jobs">Jobs</a><button type="button">›</button><a href="/parts">Parts</a>
</div>

<div>
    <a href="/jobs">Jobs</a> or <span>›</span><a href="/parts">Parts</a>
</div>

<div>
    <a href="/jobs">Prev</a><atom:button icon="right" aria-label="Next" /><a href="/parts">Parts</a>
</div>

<div>
    <atom:link href="/jobs">Jobs</atom:link>
    <atom:link href="/parts">Parts</atom:link>
</div>

<ul>
    <li><a href="/jobs">Jobs</a></li>
    <li><a href="/parts">Parts</a></li>
</ul>

<atom:page.header title="Job" :crumbs="[['label' => 'Jobs', 'href' => route('jobs')], 'WO-1']" />
