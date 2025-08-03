export default (config) => {
    return {
        trail: [],
        heading: config.heading,
    
        get breadcrumbs () {
            // Return the trail from the last element up to and including the first element with home: true
            let idx = this.trail.slice().reverse().findIndex(item => item.home)
            if (idx === -1) return []
            let start = this.trail.length - 1 - idx
            return this.trail.slice(start)
        },
    
        retrieve () {
            let wireId = document.body.querySelector('[data-atom-main] > *')?.getAttribute('wire:id')
            if (!wireId) return
    
            let component = Livewire.find(wireId)
            if (!component) return
    
            return component._breadcrumbs
        },
    
        push (items) {
            items.forEach(item => this.trail.push({ key: atom.random(), ...item }))
        },

        back () {
            let idx = this.breadcrumbs.length - 2
            if (idx > -1) Livewire.navigate(this.breadcrumbs[idx].url)
        },
    
        build () {
            let data = this.retrieve()
            let home = { ...data.home, home: true }
            let items = [home, ...data.items].filter(Boolean)
            let replace = data.replace
    
            if (!this.trail.length) this.push(items)
            else if (items.length) {
                let current = items[items.length - 1]
                let index = this.trail.findIndex(item => item.url === current.url)
    
                if (replace) this.trail.splice(this.trail.length - 1, 1, current)
                else if (index === -1) this.push([current])
                else {
                    this.trail.splice(index)
                    this.push([current])
                }
            }
        },
    }
}