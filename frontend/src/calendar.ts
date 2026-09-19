/**
 * Dates, in whichever calendar the user reads.
 *
 * Two rules hold everywhere in the app and everything here follows from them:
 *
 *  1. A date is stored, sent and compared as a Gregorian ISO string, always.
 *     The calendar setting changes how a date is *shown* and how the picker
 *     counts - never what is in the database, never what goes over the wire.
 *     That is what keeps a diary readable after someone switches calendars.
 *
 *  2. The calendar and the language are separate settings. Persian text with
 *     Gregorian dates and German text with Shamsi dates are both real requests,
 *     and neither is odd - see the CalendarSystem enum on the backend.
 *
 * Display goes through Intl, which knows both calendars and every language we
 * support. The month grid cannot: laying out a month needs to know how many
 * days it has and which weekday it starts on, and Intl will not answer that.
 * So the Jalaali arithmetic is done here.
 */

export const CALENDARS = ['gregorian', 'persian'] as const
export type CalendarSystem = (typeof CALENDARS)[number]

const STORAGE_KEY = 'fitnessapp.calendar'

/** The BCP 47 calendar subtag Intl keys off. */
const INTL_CALENDAR: Record<CalendarSystem, string> = {
  gregorian: 'gregory',
  persian: 'persian',
}

export function isCalendarSystem(value: unknown): value is CalendarSystem {
  return typeof value === 'string' && (CALENDARS as readonly string[]).includes(value)
}

/**
 * Remember the choice for the next cold start. Unlike the theme this is not
 * about avoiding a flash - nothing is painted differently - but a date shown in
 * the wrong calendar for half a second is just as wrong.
 */
export function cacheCalendar(calendar: CalendarSystem): void {
  try {
    localStorage.setItem(STORAGE_KEY, calendar)
  } catch {
    // Private windows and blocked site data. The account holds the real copy.
  }
}

/**
 * The calendar a speaker of this language would expect, before they have said
 * otherwise.
 *
 * The same rule the backend applies when it creates an account, kept in step
 * here so that a visitor who picks Farsi on the login screen sees Shamsi dates
 * straight away rather than only after registering. It is a seed and nothing
 * more: once the calendar has been chosen explicitly, changing the language
 * never moves it again.
 */
export function defaultCalendarFor(locale: string): CalendarSystem {
  return 'fa' === locale ? 'persian' : 'gregorian'
}

export function readCachedCalendar(): CalendarSystem | null {
  try {
    const cached = localStorage.getItem(STORAGE_KEY)
    return isCalendarSystem(cached) ? cached : null
  } catch {
    return null
  }
}

// ── ISO helpers ──────────────────────────────────────────────────────────────
//
// Every date in this app is a plain calendar day with no time and no zone. The
// moment one is turned into a Date at UTC midnight and formatted in a zone
// behind UTC it becomes the previous day, which is how "today" ends up showing
// yesterday's diary for anyone west of Greenwich. So dates are built at *local*
// midnight and torn apart with the local getters, never through toISOString().

export function todayIso(): string {
  return toIso(new Date())
}

export function toIso(date: Date): string {
  const month = `${date.getMonth() + 1}`.padStart(2, '0')
  const day = `${date.getDate()}`.padStart(2, '0')

  return `${date.getFullYear()}-${month}-${day}`
}

export function fromIso(iso: string): Date {
  const [year, month, day] = iso.split('-').map(Number)

  return new Date(year ?? 1970, (month ?? 1) - 1, day ?? 1)
}

/** `iso` shifted by whole days, as an ISO string. Handles month ends itself. */
export function addDays(iso: string, days: number): string {
  const date = fromIso(iso)
  date.setDate(date.getDate() + days)

  return toIso(date)
}

/** How many days lie between two ISO dates, `to` minus `from`. */
export function daysBetween(from: string, to: string): number {
  const ms = fromIso(to).getTime() - fromIso(from).getTime()

  // Rounded rather than truncated: a day that crosses a daylight-saving change
  // is 23 or 25 hours long, and truncating would lose or gain one.
  return Math.round(ms / 86_400_000)
}

// ── Formatting ───────────────────────────────────────────────────────────────

function formatter(
  locale: string,
  calendar: CalendarSystem,
  options: Intl.DateTimeFormatOptions,
): Intl.DateTimeFormat {
  // The calendar rides on the locale as a Unicode extension. Passing it as the
  // `calendar` option works too in current browsers, but the extension is the
  // form that has always been supported.
  return new Intl.DateTimeFormat(`${locale}-u-ca-${INTL_CALENDAR[calendar]}`, options)
}

/** A full date: "15 September 2026", "۲۴ شهریور ۱۴۰۵". */
export function formatDate(iso: string, locale: string, calendar: CalendarSystem): string {
  return formatter(locale, calendar, { day: 'numeric', month: 'long', year: 'numeric' }).format(
    fromIso(iso),
  )
}

/** A short date for axis labels and tight rows: "15 Sep", "۲۴ شهریور". */
export function formatShortDate(iso: string, locale: string, calendar: CalendarSystem): string {
  return formatter(locale, calendar, { day: 'numeric', month: 'short' }).format(fromIso(iso))
}

/**
 * The weekday name. Deliberately not calendar-aware: Saturday is Saturday in
 * both calendars, only its name changes with the language.
 */
export function formatWeekday(iso: string, locale: string, long = false): string {
  return new Intl.DateTimeFormat(locale, { weekday: long ? 'long' : 'short' }).format(fromIso(iso))
}

/** The heading over a month grid: "September 2026", "شهریور ۱۴۰۵". */
export function formatMonthLabel(iso: string, locale: string, calendar: CalendarSystem): string {
  return formatter(locale, calendar, { month: 'long', year: 'numeric' }).format(fromIso(iso))
}

/**
 * A day number on its own, for the cells of the grid.
 *
 * Formatted rather than stringified so Farsi gets Persian digits (۱۲۳) like
 * every other number on the screen.
 */
export function formatDayNumber(iso: string, locale: string, calendar: CalendarSystem): string {
  return formatter(locale, calendar, { day: 'numeric' }).format(fromIso(iso))
}

// ── Jalaali arithmetic ───────────────────────────────────────────────────────
//
// The conversion below is the standard algorithm published by Kazimierz M.
// Borkowski and used by jalaali-js: it determines leap years from the 33-year
// cycle breaks rather than from an approximation, and is exact for 1178-3178 in
// the Jalaali calendar - far more than a diary will ever need.
//
// It is written out here rather than pulled in as a dependency because it is
// eighty lines that never change, and a date library would be the largest thing
// in the bundle after the barcode reader.

const BREAKS = [
  -61, 9, 38, 199, 426, 686, 756, 818, 1111, 1181, 1210, 1635, 2060, 2097, 2192, 2262, 2324, 2394,
  2456, 3178,
]

/** Integer division, truncating towards zero. */
function div(a: number, b: number): number {
  return Math.trunc(a / b)
}

/**
 * The truncating remainder the conversion algorithm above is written against.
 * Negative for negative operands, like `%` in C - which is what the published
 * algorithm assumes, so it must not be "fixed" into a Euclidean modulo.
 */
function mod(a: number, b: number): number {
  return a - Math.trunc(a / b) * b
}

/**
 * A modulo that is always in [0, b), which is what calendar arithmetic wants.
 *
 * Kept apart from `mod` on purpose. Paging a month back from Farvardin, or
 * finding how far back the first of the month sits from the start of its week,
 * both go negative - and with the truncating remainder they land on month 0 and
 * on a grid that starts after the month it is drawing.
 */
function wrap(a: number, b: number): number {
  return ((a % b) + b) % b
}

interface JalaaliYearInfo {
  /** 0 when the year is a leap year. */
  leap: number
  /** The Gregorian year the Jalaali year starts in. */
  gy: number
  /** The day of March on which it starts. */
  march: number
}

function jalaaliYearInfo(jy: number): JalaaliYearInfo {
  let leapJ = -14
  let jp = BREAKS[0]!
  let jump = 0
  let leap = 0

  for (let i = 1; i < BREAKS.length; i += 1) {
    const jm = BREAKS[i]!
    jump = jm - jp

    if (jy < jm) break

    leapJ += div(jump, 33) * 8 + div(mod(jump, 33), 4)
    jp = jm
  }

  let n = jy - jp

  leapJ += div(n, 33) * 8 + div(mod(n, 33) + 3, 4)

  if (4 === mod(jump, 33) && 4 === jump - n) leapJ += 1

  const gy = jy + 621
  const leapG = div(gy, 4) - div((div(gy, 100) + 1) * 3, 4) - 150
  const march = 20 + leapJ - leapG

  if (jump - n < 6) n = n - jump + div(jump + 4, 33) * 33

  leap = mod(mod(n + 1, 33) - 1, 4)
  if (-1 === leap) leap = 4

  return { leap, gy, march }
}

/** Gregorian date to a Julian day number. */
function gregorianToJdn(gy: number, gm: number, gd: number): number {
  let d =
    div((gy + div(gm - 8, 6) + 100100) * 1461, 4) + div(153 * mod(gm + 9, 12) + 2, 5) + gd - 34840408

  d = d - div(div(gy + 100100 + div(gm - 8, 6), 100) * 3, 4) + 752

  return d
}

/** Julian day number back to a Gregorian date. */
function jdnToGregorian(jdn: number): { gy: number; gm: number; gd: number } {
  let j = 4 * jdn + 139361631

  j += div(div(4 * jdn + 183187720, 146097) * 3, 4) * 4 - 3908

  const i = div(mod(j, 1461), 4) * 5 + 308
  const gd = div(mod(i, 153), 5) + 1
  const gm = mod(div(i, 153), 12) + 1
  const gy = div(j, 1461) - 100100 + div(8 - gm, 6)

  return { gy, gm, gd }
}

export interface JalaaliDate {
  jy: number
  jm: number
  jd: number
}

export function toJalaali(date: Date): JalaaliDate {
  const jdn = gregorianToJdn(date.getFullYear(), date.getMonth() + 1, date.getDate())
  const gy = jdnToGregorian(jdn).gy

  let jy = gy - 621
  const info = jalaaliYearInfo(jy)
  let k = jdn - gregorianToJdn(info.gy, 3, info.march)

  if (k >= 0) {
    if (k <= 185) {
      return { jy, jm: 1 + div(k, 31), jd: mod(k, 31) + 1 }
    }

    k -= 186
  } else {
    jy -= 1
    k += 179

    if (1 === info.leap) k += 1
  }

  return { jy, jm: 7 + div(k, 30), jd: mod(k, 30) + 1 }
}

export function fromJalaali(jy: number, jm: number, jd: number): Date {
  const info = jalaaliYearInfo(jy)
  const jdn = gregorianToJdn(info.gy, 3, info.march) + (jm - 1) * 31 - div(jm, 7) * (jm - 7) + jd - 1
  const { gy, gm, gd } = jdnToGregorian(jdn)

  return new Date(gy, gm - 1, gd)
}

export function isLeapJalaaliYear(jy: number): boolean {
  return 0 === jalaaliYearInfo(jy).leap
}

/**
 * Days in a Jalaali month: the first six have 31, the next five have 30, and
 * Esfand has 29 except in a leap year.
 */
export function jalaaliMonthLength(jy: number, jm: number): number {
  if (jm <= 6) return 31
  if (jm <= 11) return 30

  return isLeapJalaaliYear(jy) ? 30 : 29
}

// ── The month grid ───────────────────────────────────────────────────────────

/**
 * Which weekday a week starts on, as JavaScript numbers them (0 = Sunday).
 *
 * The Iranian week starts on Saturday, and a Shamsi calendar that started on
 * Monday would be unreadable to anyone who actually uses one. For everything
 * else the app follows the ISO week.
 */
export function firstWeekday(calendar: CalendarSystem): number {
  return 'persian' === calendar ? 6 : 1
}

export interface MonthGrid {
  /** The first day of the displayed month, as an ISO date. */
  firstDay: string
  /**
   * Six rows of seven days, so the grid never changes height as the user pages
   * through months. Days from the neighbouring months are included and flagged,
   * rather than left blank - a blank cell is a dead end for the keyboard.
   */
  weeks: { iso: string; inMonth: boolean }[][]
}

/**
 * The days to draw for the month containing `iso`.
 */
export function monthGrid(iso: string, calendar: CalendarSystem): MonthGrid {
  const first = firstDayOfMonth(iso, calendar)

  // Walk back from the first of the month to the start of its week.
  const offset = wrap(fromIso(first).getDay() - firstWeekday(calendar), 7)
  const start = addDays(first, -offset)

  const weeks: { iso: string; inMonth: boolean }[][] = []

  for (let week = 0; week < 6; week += 1) {
    const row: { iso: string; inMonth: boolean }[] = []

    for (let day = 0; day < 7; day += 1) {
      const current = addDays(start, week * 7 + day)
      row.push({ iso: current, inMonth: isSameMonth(current, first, calendar) })
    }

    weeks.push(row)
  }

  return { firstDay: first, weeks }
}

export function firstDayOfMonth(iso: string, calendar: CalendarSystem): string {
  if ('persian' === calendar) {
    const { jy, jm } = toJalaali(fromIso(iso))

    return toIso(fromJalaali(jy, jm, 1))
  }

  const date = fromIso(iso)

  return toIso(new Date(date.getFullYear(), date.getMonth(), 1))
}

export function isSameMonth(a: string, b: string, calendar: CalendarSystem): boolean {
  if ('persian' === calendar) {
    const left = toJalaali(fromIso(a))
    const right = toJalaali(fromIso(b))

    return left.jy === right.jy && left.jm === right.jm
  }

  const left = fromIso(a)
  const right = fromIso(b)

  return left.getFullYear() === right.getFullYear() && left.getMonth() === right.getMonth()
}

/**
 * Page the grid by whole months.
 *
 * The day of the month is clamped rather than allowed to spill over: paging
 * back from the 31st into a 30-day month should land on the 30th, not on the
 * 1st of the month after.
 */
export function addMonths(iso: string, delta: number, calendar: CalendarSystem): string {
  if ('persian' === calendar) {
    const { jy, jm, jd } = toJalaali(fromIso(iso))
    const zeroBased = jm - 1 + delta
    const year = jy + Math.floor(zeroBased / 12)
    const month = wrap(zeroBased, 12) + 1

    return toIso(fromJalaali(year, month, Math.min(jd, jalaaliMonthLength(year, month))))
  }

  const date = fromIso(iso)
  const target = new Date(date.getFullYear(), date.getMonth() + delta, 1)
  const lastDay = new Date(target.getFullYear(), target.getMonth() + 1, 0).getDate()

  return toIso(new Date(target.getFullYear(), target.getMonth(), Math.min(date.getDate(), lastDay)))
}

/**
 * The seven weekday headings of the grid, in the order this calendar lays them
 * out and the language it is being read in.
 */
export function weekdayHeadings(locale: string, calendar: CalendarSystem): string[] {
  const start = firstWeekday(calendar)
  const formatterFor = new Intl.DateTimeFormat(locale, { weekday: 'narrow' })

  // 2024-01-07 was a Sunday, which makes it a convenient anchor for "day 0".
  const sunday = new Date(2024, 0, 7)

  return Array.from({ length: 7 }, (_, index) => {
    const day = new Date(sunday)
    day.setDate(sunday.getDate() + ((start + index) % 7))

    return formatterFor.format(day)
  })
}
