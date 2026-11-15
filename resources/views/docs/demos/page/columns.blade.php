<atom:page.columns>
    <atom:card>
        <p class="text-sm text-fg-muted">The main column holds the record's lines and its long text. Its blocks keep the page's 22px gap.</p>
    </atom:card>

    <atom:card>
        <p class="text-sm text-fg-muted">The rail sits beside this column while the column keeps 60% of the row and 320px is left for it, and under it otherwise.</p>
    </atom:card>

    <x-slot:rail label="Work order details" sticky>
        <atom:card>
            <atom:dd.group>
                <atom:dd label="Status">In progress</atom:dd>
                <atom:dd label="Due"></atom:dd>
            </atom:dd.group>
        </atom:card>

        <atom:card>
            <atom:dd.group>
                <atom:dd label="Customer">Lakeside Foods Sdn Bhd</atom:dd>
            </atom:dd.group>
        </atom:card>
    </x-slot:rail>
</atom:page.columns>
