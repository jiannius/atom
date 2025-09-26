export default (config) => {
    return {
        colors: {
            red: '#fda4af',
            green: '#0f766e',
            orange: '#ea580c',
            gray: '#d4d4d8',
        },

        init () {
            import('apexcharts').then(ApexCharts => {
                let chart = new ApexCharts.default(this.$el, {
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
                    colors: [this.getColor()],
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

                setTimeout(() => chart.render(), 200)
            })
        },

        getColor () {
            let color = config.color || ''

            if (color.startsWith('#')) return color

            return this.colors[color || 'gray']
        },
    }
}