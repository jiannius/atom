import { seriesColor } from './colors'

export default (config) => {
    // the theme listener and the first-render timer belong outside the component, so destroy() can take them off
    let onTheme = null
    let timer = null
    let rendered = false
    let destroyed = false

    return {
        chart: null,

        init () {
            import('apexcharts').then(ApexCharts => {
                // the element went before the library arrived
                if (destroyed || !this.$el.isConnected) return

                this.chart = new ApexCharts.default(this.$el, {
                    chart: {
                        type: 'area',
                        height: '100%',
                        sparkline: { enabled: true },
                    },
                    series: [{ data: config.data }],
                    fill: {
                        type: 'solid',
                        opacity: 0.2,
                    },
                    stroke: {
                        width: 1,
                        curve: 'smooth',
                    },
                    tooltip: {
                        enabled: false,
                    },
                    colors: [this.getColor()],
                })

                timer = setTimeout(() => {
                    if (!this.$el.isConnected) return

                    this.chart.render()
                    rendered = true
                }, 200)

                onTheme = () => {
                    if (this.$el.isConnected && rendered) this.chart.updateOptions({ colors: [this.getColor()] })
                }

                document.addEventListener('darkmode-changed', onTheme)
            })
        },

        destroy () {
            destroyed = true
            clearTimeout(timer)

            if (onTheme) document.removeEventListener('darkmode-changed', onTheme)

            onTheme = null
            rendered = false
        },

        /** The sparkline's ink: a tone (`success`, `danger`, `neutral`...; `green`, `red` and `gray` still read as theirs) or a hex. */
        getColor () {
            return seriesColor(config.color, 'neutral', this.$el)
        },
    }
}
