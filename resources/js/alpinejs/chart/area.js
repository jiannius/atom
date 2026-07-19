export default (config) => {
    return {
        chart: null,

        colors: {
            red: '#fda4af',
            green: '#0f766e',
            orange: '#ea580c',
            gray: '#d4d4d8',
        },

        init () {
            import('apexcharts').then(ApexCharts => {
                this.chart = new ApexCharts.default(this.$el, {
                    chart: {
                        type: 'area',
                        height: '100%',
                        sparkline: { enabled: true },
                    },
                    series: [{ 
                        data: config.data.map(data => ({
                            x: data.label,
                            y: data.value,
                        })),
                    }],
                    xaxis: {
                        type: 'category',
                    },
                    stroke: {
                        width: 1,
                        curve: 'smooth',
                    },
                    tooltip: {
                        custom: ({ series, seriesIndex, dataPointIndex, w }) => {
                            let data = config.data[dataPointIndex]
                            let tooltip = document.createElement('div')

                            tooltip.setAttribute('class', 'bg-black/80 text-sm text-white rounded-md px-3 py-1 shadow-lg')
                            tooltip.innerText = data.tooltip
                                        
                            return tooltip.outerHTML
                        },
                    },
                    ...(config.max?.value ? {
                        yaxis: {
                            min: (config.min?.value || 0) * 1.12,
                            max: config.max.value * 1.12,  // add buffer to yaxis to prevent annotation being cut off
                        },
                        annotations: {
                            yaxis: [{
                                y: config.max.value,
                                borderColor: 'black',
                                label: {
                                    borderColor: 'black',
                                    style: {
                                        color: 'white',
                                        background: 'black',
                                        fontSize: '12px',
                                    },
                                    position: 'center',
                                    text: config.max.label,
                                },
                            }],
                        },
                    } : {}),
                })

                this.$nextTick(() => {
                    this.chart.render()
                    this.setColors()
                })

                document.addEventListener('darkmode-changed', () => this.setColors())
            })
        },

        setColors () {
            if (!this.chart) return

            let dark = document.documentElement.classList.contains('dark')

            let dataColor = config.color || ''
            if (!dataColor || !dataColor.startsWith('#')) dataColor = this.colors[dataColor || 'gray']

            this.chart.updateOptions({
                colors: [dataColor],
                xaxis: {
                    labels: {
                        style: {
                            colors: config.data.map(() => (dark ? 'white' : 'black')),
                        },
                    },
                },
                ...config.max?.value ? {
                    yaxis: {
                        min: (config.min?.value || 0) * 1.12,
                        max: config.max.value * 1.12,  // add buffer to yaxis to prevent annotation being cut off
                    },
                    annotations: {
                        yaxis: [{
                            y: config.max.value,
                            borderColor: dark ? 'white' : 'black',
                            label: {
                                borderColor: dark ? 'white' : 'black',
                                style: {
                                    color: dark ? 'black' : 'white',
                                    background: dark ? 'white' : 'black',
                                    fontSize: '12px',
                                },
                                position: 'center',
                                text: config.max.label,
                            },
                        }],
                    },
                } : {}
            })
        },
    }
}