<div class="grid w-96 gap-6">
    <div data-case="search-default"><atom:table.search placeholder="Search" /></div>
    <div data-case="search-labelled"><atom:table.search label="Customers" placeholder="Search" /></div>
    <div data-case="search-full"><atom:table.search label="Customers" caption="Search by name or email" error="Something is wrong" placeholder="Search" /></div>
    <div data-case="search-tall"><atom:table.search class="h-12!" placeholder="Search" /></div>
    <div data-case="search-clearable"><atom:table.search clearable wire:model="clearable" placeholder="Search" /></div>

    <div data-case="input-icon"><atom:input icon="search" placeholder="Icon" /></div>
    <div data-case="input-icon-tall"><atom:input icon="search" class="h-12!" placeholder="Icon" /></div>
    <div data-case="input-password"><atom:input type="password" placeholder="Password" /></div>
    <div data-case="input-password-tall"><atom:input type="password" class="h-12!" placeholder="Password" /></div>
    <div data-case="input-copy-tall"><atom:input copyable class="h-12!" placeholder="Copy" /></div>

    <div data-case="select-native"><atom:select icon="search" :options="[['value' => 'a', 'label' => 'Alpha']]" /></div>
    <div data-case="select-native-tall"><atom:select icon="search" class="h-12!" :options="[['value' => 'a', 'label' => 'Alpha']]" /></div>
    <div data-case="select-listbox"><atom:select variant="listbox" icon="search" :options="[['value' => 'a', 'label' => 'Alpha']]" /></div>
    <div data-case="select-listbox-tall"><atom:select variant="listbox" icon="search" class="h-12!" :options="[['value' => 'a', 'label' => 'Alpha']]" /></div>

    <div data-case="select-filter"><atom:select variant="filter" icon="search" label="Status" :options="[['value' => 'a', 'label' => 'Alpha']]" /></div>
    <div data-case="date-range"><atom:date-picker variant="range" /></div>

    <div data-case="date"><atom:date-picker /></div>
    <div data-case="date-tall"><atom:date-picker class="h-12!" /></div>
    <div data-case="time"><atom:time-picker /></div>
    <div data-case="tel"><atom:input type="tel" /></div>
    <div data-case="tel-tall"><atom:input type="tel" class="h-12!" /></div>
    <div data-case="color"><atom:input type="color" name="c" /></div>
    <div data-case="color-tall"><atom:input type="color" class="h-12!" name="c2" /></div>
</div>
