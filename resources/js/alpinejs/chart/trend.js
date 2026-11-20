import { seriesColor } from './colors'
import chartFactory from './factory'

export default (config) => chartFactory({
    options: (component) => ({
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
        colors: [component.getColor()],
    }),
    restyle: (component) => component.chart.updateOptions({ colors: [component.getColor()] }),
    delay: 200,
}, {
    /** The sparkline's ink: a tone (`success`, `danger`, `neutral`...; `green`, `red` and `gray` still read as theirs) or a hex. */
    getColor () {
        return seriesColor(config.color, 'neutral', this.$el)
    },
})
