export default (mode = null) => {
    let applyDark = () => document.documentElement.classList.add('dark')
    let applyLight = () => document.documentElement.classList.remove('dark')

    mode = mode || window.localStorage.getItem('darkmode') || 'system'

    if (mode === 'system') {
        let media = window.matchMedia('(prefers-color-scheme: dark)')
        window.localStorage.removeItem('darkmode')
        media.matches ? applyDark() : applyLight()
    } else if (mode === 'dark') {
        window.localStorage.setItem('darkmode', 'dark')
        applyDark()
    } else if (mode === 'light') {
        window.localStorage.setItem('darkmode', 'light')
        applyLight()
    }
}