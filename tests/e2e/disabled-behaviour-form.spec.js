import { test } from '@playwright/test'
import { behaviourRows } from './disabled-behaviour-rows.js'

// One group per row, so a CI shard boundary can fall between rows (see disabled-behaviour-rows.js and the note in playwright.config.js on the file names)
test.describe.configure({ mode: 'parallel' })

behaviourRows('form')
