import dayjs from 'dayjs'

String.prototype.toDateFormat = function(format) {
    const date = dayjs(this, 'YYYY-MM-DD HH:mm:ss')
    if (date.isValid()) return date.format(format)
}

String.prototype.toDateString = function() {
    const date = dayjs(this, 'YYYY-MM-DD HH:mm:ss')
    if (date.isValid()) return date.format('DD MMM YYYY')
}

String.prototype.toDatetimeString = function() {
    const date = dayjs(this, 'YYYY-MM-DD HH:mm:ss')
    if (date.isValid()) return date.format('DD MMM YYYY h:mm A')
}

String.prototype.toTimeString = function() {
    const date = dayjs(this, 'YYYY-MM-DD HH:mm:ss')
    if (date.isValid()) return date.format('h:mm A')
}

String.prototype.fromNow = function() {
    const date = dayjs(this, 'YYYY-MM-DD HH:mm:ss')
    if (date.isValid()) return date.fromNow()
}

String.prototype.camel = function() {
    let str = this.toString().toLowerCase().replace(/[-_\s.]+(.)?/g, (_, c) => c ? c.toUpperCase() : '');
    return str.substring(0, 1).toLowerCase() + str.substring(1)
}

String.prototype.slug = function() {
    const a = 'àáâäæãåāăąçćčđďèéêëēėęěğǵḧîïíīįìıİłḿñńǹňôöòóœøōõőṕŕřßśšşșťțûüùúūǘůűųẃẍÿýžźż·/_,:;'
    const b = 'aaaaaaaaaacccddeeeeeeeegghiiiiiiiilmnnnnoooooooooprrsssssttuuuuuuuuuwxyyzzz------'
    const p = new RegExp(a.split('').join('|'), 'g')

    return this.toString().toLowerCase()
        .replace(/\s+/g, '-') // Replace spaces with -
        .replace(p, c => b.charAt(a.indexOf(c))) // Replace special characters
        .replace(/&/g, '-and-') // Replace & with 'and'
        .replace(/[^\w\-]+/g, '') // Remove all non-word characters
        .replace(/\-\-+/g, '-') // Replace multiple - with single -
        .replace(/^-+/, '') // Trim - from start of text
        .replace(/-+$/, '') // Trim - from end of text
}

String.prototype.headline = function () {
    return this
        .replace(/_/g, ' ')
        .replace(/-/g, ' ')
        .replace(/\w\S*/g, (txt) => (txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase()))
}

String.prototype.limit = function(length) {
    return this.length > length ? `${this.substring(0, length)}...` : this
}

String.prototype.nl2br = function(is_xhtml) {
    if (typeof this === 'undefined' || this === null) return ''
    let breakTag = (is_xhtml || typeof is_xhtml === 'undefined') ? '<br />' : '<br>';
    return (this + '').replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1' + breakTag + '$2');
}

String.prototype.striptags = function() {
    return this.replace(/(<([^>]+)>)/gi, "")
}