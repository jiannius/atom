import { addDays, dateOf, diffDays, timeOf } from './dates.js'

// Pure layout for <atom:calendar>: no DOM, no Alpine, no clock. It takes the payload's window days
// and items and answers where every chip sits, so it can be tested in node.

// The first and last local date an item covers. A date end is inclusive; a timed end is exclusive,
// so an item that ends at midnight does not reach into that day. An item with no end is one day.
export const span = (item) => {
    let first = dateOf(item.start)
    let last = first

    if (item.end) {
        last = dateOf(item.end)

        if (!item.allDay && timeOf(item.end) === '00:00') {
            last = addDays(last, -1)
        }
    }

    return { first, last: last < first ? first : last }
}

// Whether the month view draws the item as a bar across days (all-day, or crossing a date) rather
// than as one row of dot, time and title.
export const isBar = (item) => {
    let { first, last } = span(item)

    return item.allDay || last > first
}

const byStart = (a, b) => (a.item.start < b.item.start ? -1 : a.item.start > b.item.start ? 1 : a.index - b.index)

// Every item on one date, bars (all-day and multi-day) first, then timed items by start, ties in the
// order the host gave them. This is the day popover's list.
export const dayEntries = (items, date) => items
    .map((item, index) => ({ item, index, ...span(item) }))
    .filter((entry) => entry.first <= date && entry.last >= date)
    .sort((a, b) => {
        let bar = Number(isBar(b.item)) - Number(isBar(a.item))

        return bar || byStart(a, b)
    })
    .map((entry) => entry.item)

// Lay the window's weeks out for the month view.
//
// `days` is the payload's window days (a multiple of seven), `items` the payload's items. Every week
// is one grid row of seven columns: the day numbers sit in grid row 1 and the lanes under them from
// grid row 2. An all-day or multi-day item is a bar that takes the lowest lane free for its whole
// run inside the week (longer and earlier first, so they stack the way they read); a timed item
// within one day is a dot row that takes the first row its day has free. Only `max` rows are shown
// on a day, then "+N more" in the row after them; `max` null shows every row.
//
// Returns weeks of { key, days, chips, more }. A day is { date, number, col, weekend, outside,
// today, past, count, dots, extra }, where `dots` are up to four of the day's items for the narrow
// layout. A chip is its item plus { key, kind: 'bar' | 'dot', col, span, row, before, after }.
export const monthLayout = (days, items, { max = 3, today = null } = {}) => {
    let entries = items.map((item, index) => ({ item, index, ...span(item), bar: isBar(item) }))
    let weeks = []

    for (let start = 0; start < days.length; start += 7) {
        let week = days.slice(start, start + 7)
        let first = week[0].date
        let last = week[week.length - 1].date

        let bars = []
        let dots = week.map(() => [])

        for (let entry of entries) {
            if (entry.last < first || entry.first > last) continue

            if (entry.bar) {
                bars.push({
                    entry,
                    startCol: Math.max(0, diffDays(first, entry.first)),
                    endCol: Math.min(week.length - 1, diffDays(first, entry.last)),
                    before: entry.first < first,
                    after: entry.last > last,
                })
            } else {
                dots[diffDays(first, entry.first)].push(entry)
            }
        }

        bars.sort((a, b) => (a.startCol - b.startCol)
            || ((b.endCol - b.startCol) - (a.endCol - a.startCol))
            || (Number(b.entry.item.allDay) - Number(a.entry.item.allDay))
            || byStart(a.entry, b.entry))

        // lanes: the lowest one free across the bar's whole run
        let lanes = []
        let used = week.map(() => new Set())

        for (let bar of bars) {
            let lane = 0

            while (lanes[lane]?.some((other) => other.startCol <= bar.endCol && other.endCol >= bar.startCol)) lane++

            lanes[lane] ??= []
            lanes[lane].push(bar)
            bar.lane = lane

            for (let col = bar.startCol; col <= bar.endCol; col++) used[col].add(lane)
        }

        // a dot row takes the first row its own day has free
        let placed = []

        dots.forEach((list, col) => {
            list.sort(byStart).forEach((entry) => {
                let row = 0

                while (used[col].has(row)) row++

                used[col].add(row)
                placed.push({ entry, col, lane: row })
            })
        })

        let chips = []
        let counts = week.map(() => ({ total: 0, hidden: 0 }))

        let add = (entry, col, lane, length, extra) => {
            let shown = max === null || lane < max

            for (let c = col; c < col + length; c++) {
                counts[c].total++
                if (!shown) counts[c].hidden++
            }

            if (shown) {
                chips.push({
                    ...entry.item,
                    key: first + ':' + entry.item.id,
                    kind: entry.bar ? 'bar' : 'dot',
                    col: col + 1,
                    span: length,
                    row: lane + 2,
                    before: false,
                    after: false,
                    ...extra,
                })
            }
        }

        bars.forEach((bar) => add(bar.entry, bar.startCol, bar.lane, bar.endCol - bar.startCol + 1, { before: bar.before, after: bar.after }))
        placed.forEach((dot) => add(dot.entry, dot.col, dot.lane, 1, {}))

        let more = []

        counts.forEach((count, col) => {
            if (count.hidden > 0) {
                more.push({ key: first + ':more:' + week[col].date, date: week[col].date, col: col + 1, row: max + 2, count: count.hidden })
            }
        })

        weeks.push({
            key: first,
            chips,
            more,
            days: week.map((day, col) => {
                let list = dayEntries(items, day.date)

                return {
                    date: day.date,
                    number: Number(day.date.slice(8, 10)),
                    col: col + 1,
                    weekend: Boolean(day.weekend),
                    outside: Boolean(day.outside),
                    today: day.date === today,
                    past: today !== null && day.date < today,
                    count: counts[col].total,
                    dots: list.slice(0, 4),
                    extra: Math.max(0, list.length - 4),
                }
            }),
        })
    }

    return weeks
}
