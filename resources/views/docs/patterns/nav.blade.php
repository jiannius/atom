{{-- The nav slot both app shells share. It is plain navlist markup and knows nothing about the shell it sits in: the
     sidebar draws it as a column, the top nav as bar items, and the group below as a dropdown. The host decides what
     each user sees (wrap an item in @can as usual) and which shell holds it. --}}
@php
$page = request()->query('page', 'invoices');
@endphp

<atom:navlist>
    <atom:navlist.item href="?page=dashboard" icon="dashboard" count="3" :current="$page === 'dashboard'">Dashboard</atom:navlist.item>
    <atom:navlist.item href="?page=invoices" icon="document" :current="$page === 'invoices'">Invoices</atom:navlist.item>
    <atom:navlist.item href="?page=customers" icon="users" :current="$page === 'customers'">Customers</atom:navlist.item>

    <atom:navlist.group heading="Reports">
        <atom:navlist.item href="?page=sales" icon="bar-chart" :current="$page === 'sales'">Sales</atom:navlist.item>
        <atom:navlist.item href="?page=expenses" icon="wallet" :current="$page === 'expenses'">Expenses</atom:navlist.item>
    </atom:navlist.group>

    <atom:navlist.group heading="Settings" expandable persist-key="patterns.settings">
        <atom:navlist.item href="?page=team" :current="$page === 'team'">Team</atom:navlist.item>
        <atom:navlist.item href="?page=billing" :current="$page === 'billing'">Billing</atom:navlist.item>
    </atom:navlist.group>
</atom:navlist>
