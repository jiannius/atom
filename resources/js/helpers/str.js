import dayjs from 'dayjs'

/**
 * String helpers: `atom.str.headline('order_status')`. The date ones read `YYYY-MM-DD HH:mm:ss`
 * (the shared dayjs ignores the format and parses ISO-like strings) and return undefined when invalid.
 */
const date = (value) => {
    const parsed = dayjs(value, 'YYYY-MM-DD HH:mm:ss')
    return parsed.isValid() ? parsed : null
}

export default {
    toDateFormat: (value, format) => date(value)?.format(format),
    toDateString: (value) => date(value)?.format('DD MMM YYYY'),
    toDatetimeString: (value) => date(value)?.format('DD MMM YYYY h:mm A'),
    toTimeString: (value) => date(value)?.format('h:mm A'),
    fromNow: (value) => date(value)?.fromNow(),

    camel: (value) => {
        let str = value.toString().toLowerCase().replace(/[-_\s.]+(.)?/g, (_, c) => c ? c.toUpperCase() : '');
        return str.substring(0, 1).toLowerCase() + str.substring(1)
    },

    slug: (value) => {
        const a = 'àáâäæãåāăąçćčđďèéêëēėęěğǵḧîïíīįìıİłḿñńǹňôöòóœøōõőṕŕřßśšşșťțûüùúūǘůűųẃẍÿýžźż·/_,:;'
        const b = 'aaaaaaaaaacccddeeeeeeeegghiiiiiiiilmnnnnoooooooooprrsssssttuuuuuuuuuwxyyzzz------'
        const p = new RegExp(a.split('').join('|'), 'g')

        return value.toString().toLowerCase()
            .replace(/\s+/g, '-') // Replace spaces with -
            .replace(p, c => b.charAt(a.indexOf(c))) // Replace special characters
            .replace(/&/g, '-and-') // Replace & with 'and'
            .replace(/[^\w\-]+/g, '') // Remove all non-word characters
            .replace(/\-\-+/g, '-') // Replace multiple - with single -
            .replace(/^-+/, '') // Trim - from start of text
            .replace(/-+$/, '') // Trim - from end of text
    },

    headline: (value) => value
        .replace(/_/g, ' ')
        .replace(/-/g, ' ')
        .replace(/\w\S*/g, (txt) => (txt.charAt(0).toUpperCase() + txt.substr(1).toLowerCase())),

    limit: (value, length) => value.length > length ? `${value.substring(0, length)}...` : value,

    nl2br: (value, is_xhtml) => {
        if (typeof value === 'undefined' || value === null) return ''
        let breakTag = (is_xhtml || typeof is_xhtml === 'undefined') ? '<br />' : '<br>';
        return (value + '').replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1' + breakTag + '$2');
    },

    striptags: (value) => value.replace(/(<([^>]+)>)/gi, ""),
}
