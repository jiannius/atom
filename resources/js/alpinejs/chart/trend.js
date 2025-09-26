export default (config) => {
    return {
        colors: {
            green: '#6ee7b7',
            red: '#fda4af',
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