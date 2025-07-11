<div
x-data="{
    show: false,

    get selectElement () {
        return $el.parentNode.querySelector('select')
    },

    init () {
        this.show = this.hasValue()
        this.selectElement.addEventListener('change', () => this.show = this.hasValue())
    },

    hasValue () {
        return !empty(this.selectElement.value)
    },
}"
x-on:click.stop="() => {
    selectElement.value = ''
    selectElement.dispatchEvent(new CustomEvent('change'))
}"
x-bind:class="!show && 'pointer-events-none'"
class="z-1 absolute top-0 bottom-0 flex items-center justify-center right-0"
data-atom-select-clear>
    <atom:icon.close x-show="show" class="text-muted-foreground mr-3 size-5 hover:text-muted"/>
    <atom:icon.dropdown x-show="!show"/>
</div>
