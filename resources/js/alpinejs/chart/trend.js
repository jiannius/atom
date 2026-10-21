import { seriesColor } from './colors'

export default (config) => {
    return {
        chart: null,

        init () {
            import('apexcharts').then(ApexCharts => {
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

                setTimeout(() => this.chart.render(), 200)

                document.addEventListener('darkmode-changed', () => this.chart.updateOptions({ colors: [this.getColor()] }))
            })
        },

        /** The sparkline's ink: a tone (`success`, `danger`, `neutral`...; `green`, `red` and `gray` still read as theirs) or a hex. */
        getColor () {
            return seriesColor(config.color, 'neutral')
        },
    }
}
