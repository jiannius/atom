import { dateOf, diffDays, minuteOf } from './dates.js'
import { dayEntries, packBars, span } from './layout.js'

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
    if (item.allDay) return null

    let { first, last } = span(item)

    if (date < first || date > last) return null

    let start = date === first ? minuteOf(item.start) : 0
    let end = item.end ? (date === dateOf(item.end) ? minuteOf(item.end) : DAY_MINUTES) : start

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

// How far apart (minutes) two items start and still count as starting together, in the cascade.
export const CASCADE_TOGETHER = 15

// How much of the column's width each level of the cascade is moved in, and the most it moves in all.
export const CASCADE_STEP = 12
export const CASCADE_MAX = 36

// The other way to lay overlapping items out, as Google Calendar and Schedule-X do: not side by side but one over
// the next, each later one moved in from the left and drawn on top, so a title stays as wide as the column allows.
// Items that start together (within CASCADE_TOGETHER minutes) share a level side by side, since the later one would
// cover the earlier one's title; a later start goes one level in over every earlier item it overlaps. Same clusters as
// packColumns. Writes `left`, `width` (percent of the column) and `level` on each segment, and returns them in start order.
export const packCascade = (segments) => {
    let sorted = [...segments].sort((a, b) => (a.drawnStart - b.drawnStart) || (b.drawnEnd - a.drawnEnd) || (a.index - b.index))
    let clusters = []

    for (let segment of sorted) {
        let last = clusters[clusters.length - 1]

        if (last && segment.drawnStart < last.end) {
            last.segments.push(segment)
            last.end = Math.max(last.end, segment.drawnEnd)
        } else {
            clusters.push({ segments: [segment], end: segment.drawnEnd })
        }
    }

    for (let { segments: cluster } of clusters) {
        // groups of items that start together
        let groups = []

        for (let segment of cluster) {
            let group = groups[groups.length - 1]

            if (group && segment.drawnStart - group.start < CASCADE_TOGETHER) group.segments.push(segment)
            else groups.push({ start: segment.drawnStart, segments: [segment], level: 0 })
        }

        // a group goes one level in from the deepest earlier group it overlaps
        groups.forEach((group, i) => {
            let end = Math.max(...group.segments.map((segment) => segment.drawnEnd))

            group.end = end
            group.level = 0

            for (let earlier of groups.slice(0, i)) {
                if (earlier.end > group.start) group.level = Math.max(group.level, earlier.level + 1)
            }
        })

        let depth = Math.max(...groups.map((group) => group.level))
        let step = depth ? Math.min(CASCADE_STEP, CASCADE_MAX / depth) : 0

        for (let group of groups) {
            let left = group.level * step
            let each = (100 - left) / group.segments.length

            group.segments.forEach((segment, i) => {
                segment.left = left + i * each
                segment.width = each
                segment.level = group.level
            })
        }
    }

    return sorted
}

const round = (value) => Math.round(value * 10000) / 10000

// One date's timed segments as blocks: where each sits in the day (top and height, as a percentage of the day),
// which lane (left and width, as a percentage of the column), and whether it carries on from or into another day.
export const columnBlocks = (items, date, { slotDuration = 30, overlap = 'lanes', lifted = null } = {}) => {
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

    let cascade = overlap === 'cascade'
    let placed = cascade ? packCascade(segments) : packColumns(segments)

    return placed.map((segment) => ({
        ...segment.item,
        key: date + ':' + segment.item.id,
        kind: 'block',
        date,
        startMinute: segment.start,
        endMinute: segment.end,
        top: round(segment.start / DAY_MINUTES * 100),
        height: round((segment.end - segment.start) / DAY_MINUTES * 100),
        left: round(cascade ? segment.left : segment.column / segment.columns * 100),
        width: round(cascade ? segment.width : 100 / segment.columns),
        // the cascade stacks: a later level is in front of the one before it
        level: cascade ? segment.level : 0,
        column: cascade ? undefined : segment.column,
        columns: cascade ? undefined : segment.columns,
        compact: segment.end - segment.start < COMPACT_MINUTES,
        before: segment.before,
        after: segment.after,
        lifted: lifted !== null && String(segment.item.id) === String(lifted),
    }))
}

// What a gesture in progress draws on one date's column, as shapes placed like blocks (top and height in percent of the day,
// the whole width): `preview` is { kind: 'select' | 'pending' | 'move' | 'resize', start, end, id? } in wall-clock strings. A
// selection and a pending item are plain boxes; a move or a resize is the item `id` as it would be at the new time, so it
// carries that item's fields. Nothing here changes an item: the original stays where it is, drawn lifted (see columnBlocks).
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
        level: 0,
        compact: part.end - part.start < COMPACT_MINUTES,
        before: part.before,
        after: part.after,
    }]
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

    return { chips, more }
}

// The time grid: the shown `days` (the payload's window days, contiguous) as columns of timed blocks, and the all-day
// row over them. A column is { date, number, col, weekend, outside, today, past, count, blocks, preview }, `count` being every
// item of the day, all-day ones too, for its label, and `preview` what a gesture in progress draws there.
export const timeGrid = (days, items, { today = null, max = 3, slotDuration = 30, overlap = 'lanes', preview = null } = {}) => ({
    columns: days.map((day, col) => {
        let blocks = columnBlocks(items, day.date, { slotDuration, overlap, lifted: preview && ['move', 'resize'].includes(preview.kind) ? preview.id : null })
        let shapes = previewBlocks(preview, items, day.date, { slotDuration })

        // a resize stays in the lane of the item it resizes (a move has no lane yet: it is where it is dropped)
        if (preview?.kind === 'resize') {
            let lane = blocks.find((block) => block.lifted)

            if (lane) shapes.forEach((shape) => Object.assign(shape, { left: lane.left, width: lane.width, level: lane.level }))
        }

        return {
            date: day.date,
            number: Number(day.date.slice(8, 10)),
            col: col + 1,
            weekend: Boolean(day.weekend),
            outside: Boolean(day.outside),
            today: day.date === today,
            past: today !== null && day.date < today,
            count: dayEntries(items, day.date).length,
            blocks,
            preview: shapes,
        }
    }),
    allDay: allDayRow(days, items, { max }),
})
