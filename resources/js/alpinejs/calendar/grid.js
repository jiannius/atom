import { dateOf, diffDays, minuteOf } from './dates.js'
import { dayEntries, packBars, span } from './layout.js'

// Pure layout for the time grid of <atom:calendar>'s week and day views: no DOM, no Alpine, no clock. Like
// layout.js it takes the payload's days and items and answers where everything sits, so it can be tested in node.

export const DAY_MINUTES = 1440

// The shortest an item is drawn: 20px, which at the grid's 3rem an hour is a little over 25 minutes. Lanes are
// worked out on what is drawn, so two short items close together sit side by side instead of on top of each other.
export const MIN_DRAWN_MINUTES = 25

// A timed item under this long has no room for a second line: it prints "Title · 10:00" in one.
export const COMPACT_MINUTES = 45

// The part of a timed item that falls on one date, in wall-clock minutes from that date's midnight, or null.
// An item with no end is one `slotDuration` long, clamped at the end of the day; an end that is not after the
// start (the data layer already turns those into no end) is treated the same way. Nothing here knows about a
// daylight-saving change: a day is always 24 rows of wall-clock time, so the hour that does not exist is an empty
// row, and an item that spans it is as tall as the wall clock says.
export const segmentOn = (item, date, slotDuration = 30) => {
    if (item.allDay) return null

    let { first, last } = span(item)

    if (date < first || date > last) return null

    let start = date === first ? minuteOf(item.start) : 0
    let end = item.end ? (date === dateOf(item.end) ? minuteOf(item.end) : DAY_MINUTES) : start + slotDuration

    if (end <= start) end = start + slotDuration

    return { start, end: Math.min(end, DAY_MINUTES), before: date > first, after: date < last }
}

// Side-by-side lanes for one day's segments. Segments that overlap, one through another, are a cluster, and every
// segment of a cluster is as wide as one of its columns, so the widths stay put however the cluster is read.
// Within a cluster the earlier segment goes first, the longer one first on a tie, and each takes the first column
// whose last segment has ended. Writes `column` and `columns` on each segment, and returns them in start order.
export const packColumns = (segments) => {
    let sorted = [...segments].sort((a, b) => (a.start - b.start) || (b.drawnEnd - a.drawnEnd) || (a.index - b.index))
    let cluster = []
    let clusterEnd = -1
    let ends = []

    let close = () => {
        let columns = Math.max(0, ...cluster.map((segment) => segment.column + 1))

        cluster.forEach((segment) => { segment.columns = columns })
        cluster = []
        ends = []
    }

    for (let segment of sorted) {
        if (cluster.length && segment.start >= clusterEnd) close()

        let column = ends.findIndex((end) => end <= segment.start)

        if (column === -1) column = ends.length

        ends[column] = segment.drawnEnd
        segment.column = column
        clusterEnd = cluster.length ? Math.max(clusterEnd, segment.drawnEnd) : segment.drawnEnd
        cluster.push(segment)
    }

    close()

    return sorted
}

const round = (value) => Math.round(value * 10000) / 10000

// One date's timed segments as blocks: where each sits in the day (top and height, as a percentage of the day),
// which lane (left and width, as a percentage of the column), and whether it carries on from or into another day.
export const columnBlocks = (items, date, { slotDuration = 30 } = {}) => {
    let segments = []

    items.forEach((item, index) => {
        let part = segmentOn(item, date, slotDuration)

        if (part) {
            segments.push({
                item,
                index,
                ...part,
                drawnEnd: Math.max(part.end, Math.min(part.start + MIN_DRAWN_MINUTES, DAY_MINUTES)),
            })
        }
    })

    return packColumns(segments).map((segment) => ({
        ...segment.item,
        key: date + ':' + segment.item.id,
        kind: 'block',
        date,
        startMinute: segment.start,
        endMinute: segment.end,
        top: round(segment.start / DAY_MINUTES * 100),
        height: round((segment.end - segment.start) / DAY_MINUTES * 100),
        left: round(segment.column / segment.columns * 100),
        width: round(100 / segment.columns),
        column: segment.column,
        columns: segment.columns,
        compact: segment.end - segment.start < COMPACT_MINUTES,
        before: segment.before,
        after: segment.after,
    }))
}

// The all-day row: every all-day item over the shown days as a bar in a lane, the same bars as the month view's,
// with `max` lanes and a "+N more" after them (`max` null shows every lane). A bar is its item plus
// { key, kind: 'bar', col, span, row, before, after }, where `row` counts from 1; `rows` is how many rows the row needs.
export const allDayRow = (days, items, { max = 3 } = {}) => {
    if (!days.length) return { chips: [], more: [], rows: 0 }

    let first = days[0].date
    let last = days[days.length - 1].date
    let bars = []

    items.forEach((item, index) => {
        if (!item.allDay) return

        let entry = { item, index, ...span(item) }

        if (entry.last < first || entry.first > last) return

        bars.push({
            entry,
            startCol: Math.max(0, diffDays(first, entry.first)),
            endCol: Math.min(days.length - 1, diffDays(first, entry.last)),
            before: entry.first < first,
            after: entry.last > last,
        })
    })

    packBars(bars)

    let hidden = days.map(() => 0)
    let chips = []

    for (let bar of bars) {
        if (max === null || bar.lane < max) {
            chips.push({
                ...bar.entry.item,
                key: first + ':' + bar.entry.item.id,
                kind: 'bar',
                col: bar.startCol + 1,
                span: bar.endCol - bar.startCol + 1,
                row: bar.lane + 1,
                before: bar.before,
                after: bar.after,
            })
        } else {
            for (let col = bar.startCol; col <= bar.endCol; col++) hidden[col]++
        }
    }

    let more = []

    hidden.forEach((count, col) => {
        if (count > 0) {
            more.push({ key: first + ':more:' + days[col].date, date: days[col].date, col: col + 1, row: max + 1, count })
        }
    })

    let lanes = bars.length ? Math.max(...bars.map((bar) => bar.lane)) + 1 : 0

    return { chips, more, rows: Math.min(max ?? lanes, lanes) + (more.length ? 1 : 0) }
}

// The time grid: the shown `days` (the payload's window days, contiguous) as columns of timed blocks, and the all-day
// row over them. A column is { date, number, col, weekend, outside, today, past, count, blocks }, `count` being every
// item of the day, all-day ones too, for its label.
export const timeGrid = (days, items, { today = null, max = 3, slotDuration = 30 } = {}) => ({
    columns: days.map((day, col) => ({
        date: day.date,
        number: Number(day.date.slice(8, 10)),
        col: col + 1,
        weekend: Boolean(day.weekend),
        outside: Boolean(day.outside),
        today: day.date === today,
        past: today !== null && day.date < today,
        count: dayEntries(items, day.date).length,
        blocks: columnBlocks(items, day.date, { slotDuration }),
    })),
    allDay: allDayRow(days, items, { max }),
})
