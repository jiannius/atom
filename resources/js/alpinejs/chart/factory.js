import { canvasOf } from './colors'

/**
 * The reference line a chart draws at its `max` (`line` and `onLine` are the colours `chrome()` reads from the chart's own element).
 */
export const maxAnnotation = (config, { line, onLine }) => ({
    yaxis: [{
        y: config.max.value,
        borderColor: line,
        label: {
            borderColor: line,
            style: {
                color: onLine,
                background: line,
                fontSize: '12px',
            },
            position: 'center',
            text: config.max.label,
        },
    }],
})

/**
 * What the chart factories share: ApexCharts is loaded on demand, the chart is built once the element is still in the page, rendered
 * (in the next tick, or after `delay` ms), and drawn again when the theme changes. destroy() takes the document listener and the timer
 * off again.
 *
 * - `options(component)` is the ApexCharts options object.
 * - `restyle(component)` runs on `darkmode-changed` once the chart is rendered, and, with `restyleAfterRender`, right after the first render.
 * - `extra` is merged into the component (the chart's own methods).
 */
export default ({ options, restyle, restyleAfterRender = false, delay = null }, extra = {}) => {
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

                this.chart = new ApexCharts.default(canvasOf(this.$el), options(this))

                const draw = () => {
                    if (!this.$el.isConnected) return

                    this.chart.render()
                    rendered = true
                    if (restyleAfterRender) restyle(this)
                }

                if (delay === null) this.$nextTick(draw)
                else timer = setTimeout(draw, delay)

                onTheme = () => {
                    if (this.$el.isConnected && rendered) restyle(this)
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

        ...extra,
    }
}
