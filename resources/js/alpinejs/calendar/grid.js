import { dateOf, diffDays, minuteOf, parseDate } from './dates.js'
import { entriesOf, entriesOn, isPlaceable, packBars, span } from './layout.js'

// Pure layout for the time grid of <atom:calendar>'s week and day views: no DOM, no Alpine, no clock. Like
// layout.js it takes the payload's days and items and answers where everything sits, so it can be tested in node.

export const DAY_MINUTES = 1440

// The shortest an item is drawn: half an hour of the grid, whatever the hour is tall (atom.css gives a block
// `min-height: calc(var(--atom-calendar-hour) / 2)`, and the browser clamps it the same way). Lanes are worked out
// on what is drawn, so two short items close together sit side by side instead of on top of each other.
export const MIN_DRAWN_MINUTES = 30

// A timed item under this long has no room for a second line: it prints "Title · 10:00" in one. Two lines and the block's
// padding are 2.25rem of text-xs, which a 3.5rem hour gives from 39 minutes; 45 leaves room to spare.
export const COMPACT_MINUTES = 45

// The part of a timed item that falls on one date, in wall-clock minutes from that date's midnight, or null.
// An item with no end is one `slotDuration` long, clamped at the end of the day; an end that is not after the
// start (the data layer already turns those into no end) is treated the same way. Nothing here knows about a
// daylight-saving change: a day is always 24 rows of wall-clock time, so the hour that does not exist is an empty
// row, and an item that spans it is as tall as the wall clock says.
export const segmentOn = (item, date, slotDuration = 30) => {
    if (item.allDay || !isPlaceable(item)) return null

    let { first, last } = span(item)

    if (date < first || date > last) return null

    let start = date === first ? minuteOf(item.start) : 0
    let end = item.end && parseDate(dateOf(item.end)) ? (date === dateOf(item.end) ? minuteOf(item.end) : DAY_MINUTES) : start

    if (end <= start) end = start + slotDuration

    return { start, end: Math.min(end, DAY_MINUTES), before: date > first, after: date < last }
}

// Side-by-side lanes for one day's segments, on what is drawn of them: `drawnStart` and `drawnEnd`, which a segment
// that is short or at the end of the day has moved out from its time (see columnBlocks). Segments that overlap, one through another, are a cluster, and every
// segment of a cluster is as wide as one of its columns, so the widths stay put however the cluster is read.
// Within a cluster the earlier segment goes first, the longer one first on a tie, and each takes the first column
// whose last segment has ended. Writes `column` and `columns` on each segment, and returns them in start order.
export const packColumns = (segments) => {
    let sorted = [...segments].sort((a, b) => (a.drawnStart - b.drawnStart) || (b.drawnEnd - a.drawnEnd) || (a.index - b.index))
    let cluster = []
    let clusterEnd = -1
    let ends = []

    let close = () => {
        let columns = cluster.reduce((most, segment) => Math.max(most, segment.column + 1), 0)

        cluster.forEach((segment) => { segment.columns = columns })
        cluster = []
        ends = []
    }

    for (let segment of sorted) {
        if (cluster.length && segment.drawnStart >= clusterEnd) close()

        let column = ends.findIndex((end) => end <= segment.drawnStart)

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
            // A block is never drawn shorter than MIN_DRAWN_MINUTES, and one that would run past the end of the day is moved
            // up to fit: what it covers is from drawnStart to drawnEnd, and the lanes are worked out on that.
            let drawnStart = Math.min(part.start, DAY_MINUTES - MIN_DRAWN_MINUTES)

            segments.push({
                item,
                index,
                ...part,
                drawnStart,
                drawnEnd: Math.max(part.end, drawnStart + MIN_DRAWN_MINUTES),
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

// What a gesture in progress draws on one date's column, as shapes placed like blocks (top and height in percent of the day,
// the whole width): `preview` is { kind: 'select' | 'pending' | 'move' | 'resize', start, end, id? } in wall-clock strings. A
// selection and a pending item are plain boxes; a move or a resize is the item `id` as it would be at the new time, so it
// carries that item's fields. Nothing here changes an item: the original stays where it is, drawn lifted (see isLifted).
export const previewBlocks = (preview, items, date, { slotDuration = 30 } = {}) => {
    if (!preview || preview.allDay || !preview.start) return []

    let part = segmentOn({ start: preview.start, end: preview.end, allDay: false }, date, slotDuration)

    if (!part) return []

    let item = preview.id === undefined || preview.id === null ? null : items.find((candidate) => String(candidate.id) === String(preview.id))

    return [{
        ...(item ?? {}),
        key: 'preview:' + date,
        kind: preview.kind,
        title: preview.title ?? item?.title ?? '',
        date,
        // the whole of the gesture's range, for its label; the part on this date is what is drawn
        start: preview.start,
        end: preview.end,
        top: round(part.start / DAY_MINUTES * 100),
        height: round((part.end - part.start) / DAY_MINUTES * 100),
        left: 0,
        width: 100,
        compact: part.end - part.start < COMPACT_MINUTES,
        before: part.before,
        after: part.after,
    }]
}

// What a selection, or an item waiting for the host, covers in the all-day row: the part of its dates over the shown `days`, as shares of the
// row's width (the same percentages the month view's selection takes), or null. The row is where an all-day range lives: a timed one is in the
// columns (previewBlocks), and a gesture that has no part in the shown days draws nothing.
export const allDayShape = (days, preview) => {
    if (!preview?.allDay || !preview.start || !days.length || !['select', 'pending'].includes(preview.kind)) return null

    let { first, last } = span({ start: preview.start, end: preview.end, allDay: true })
    let from = days[0].date
    let to = days[days.length - 1].date

    if (last < from || first > to) return null

    let startCol = Math.max(0, diffDays(from, first))
    let endCol = Math.min(days.length - 1, diffDays(from, last))

    return {
        key: 'allday-preview',
        kind: preview.kind,
        title: preview.title ?? '',
        left: startCol / days.length * 100,
        width: (endCol - startCol + 1) / days.length * 100,
        before: first < from,
        after: last > to,
    }
}

// The all-day row: every all-day item over the shown days as a bar in a lane, the same bars as the month view's,
// with `max` lanes and a "+N more" after them (`max` null shows every lane). A bar is its item plus
// { key, kind: 'bar', col, span, row, before, after }, where `row` counts from 1.
export const allDayRow = (days, items, { max = 3 } = {}) => {
    if (!days.length) return { chips: [], more: [] }

    let first = days[0].date
    let last = days[days.length - 1].date
    let bars = []

    items.forEach((item, index) => {
        if (!item.allDay || !isPlaceable(item)) return

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

    return { chips, more }
}

// The time grid: the shown `days` (the payload's window days, contiguous) as columns of timed blocks, and the all-day
// row over them. A column is { date, number, col, weekend, outside, today, past, count, blocks }, `count` being every
// item of the day, all-day ones too, for its label. Nothing here knows of a gesture in progress: what it draws is
// `previewShapes`, so that a preview that changes does not lay the blocks out again.
export const timeGrid = (days, items, { today = null, max = 3, slotDuration = 30 } = {}) => {
    // an item whose dates do not parse is left out here, once, and not found out again by every column
    let entries = entriesOf(items)
    let placeable = entries.map((entry) => entry.item)

    return {
        columns: days.map((day, col) => ({
            date: day.date,
            number: Number(day.date.slice(8, 10)),
            col: col + 1,
            weekend: Boolean(day.weekend),
            outside: Boolean(day.outside),
            today: day.date === today,
            past: today !== null && day.date < today,
            count: entriesOn(entries, day.date).length,
            blocks: columnBlocks(placeable, day.date, { slotDuration }),
        })),
        allDay: allDayRow(days, placeable, { max }),
    }
}

// Whether `id` is the item a move or a resize in progress is lifting: the original stays where it is, drawn faint. A selection or an item
// waiting for the host lifts nothing.
export const isLifted = (preview, id) => Boolean(preview)
    && ['move', 'resize'].includes(preview.kind)
    && preview.id !== undefined && preview.id !== null
    && String(preview.id) === String(id)

// What a gesture in progress draws in each of the grid's columns: { [date]: shapes }, a key for every column. A resize stays in
// the lane of the item it resizes, so it is given that block's `left` and `width`. A move is laid out among what is on the day it is over,
// as the item would be if it were dropped there (the original left out), so that it takes a lane beside the items it overlaps and does not
// cover them.
export const previewShapes = (columns, items, preview, { slotDuration = 30 } = {}) => {
    let moved = null

    if (preview?.kind === 'move' && !preview.allDay && preview.start) {
        let item = items.find((candidate) => String(candidate.id) === String(preview.id))

        if (item) moved = { ...item, start: preview.start, end: preview.end, allDay: false }
    }

    return Object.fromEntries(columns.map((column) => {
        let shapes = previewBlocks(preview, items, column.date, { slotDuration })

        if (preview?.kind === 'resize') {
            let lane = column.blocks.find((block) => isLifted(preview, block.id))

            if (lane) shapes.forEach((shape) => Object.assign(shape, { left: lane.left, width: lane.width }))
        }

        if (moved && shapes.length) {
            let others = items.filter((candidate) => String(candidate.id) !== String(moved.id))
            let lane = columnBlocks([...others, moved], column.date, { slotDuration }).find((block) => String(block.id) === String(moved.id))

            if (lane) shapes.forEach((shape) => Object.assign(shape, { left: lane.left, width: lane.width }))
        }

        return [column.date, shapes]
    }))
}

// The ghost of an all-day item being moved or resized, in the all-day row: the item as a bar over the shown `days` it would then cover, placed
// like the row's own bars ({ col, span, row: 1 }, the first lane, in front of whatever is there), or null. A timed item's ghost is in the
// columns (previewBlocks), and one that has no part in the shown days draws nothing.
export const allDayGhost = (days, items, preview) => {
    if (!preview?.allDay || !preview.start || !days.length || !['move', 'resize'].includes(preview.kind)) return null

    let item = items.find((candidate) => String(candidate.id) === String(preview.id))
    let { first, last } = span({ start: preview.start, end: preview.end, allDay: true })
    let from = days[0].date
    let to = days[days.length - 1].date

    if (last < from || first > to) return null

    let startCol = Math.max(0, diffDays(from, first))
    let endCol = Math.min(days.length - 1, diffDays(from, last))

    return {
        ...(item ?? {}),
        key: 'allday-ghost',
        kind: preview.kind,
        title: preview.title ?? item?.title ?? '',
        start: preview.start,
        end: preview.end,
        col: startCol + 1,
        span: endCol - startCol + 1,
        row: 1,
        before: first < from,
        after: last > to,
    }
}
