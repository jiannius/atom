// Compiles input.css with a consumer's Tailwind, once per version directory (floor = the hosts' minimum,
// latest = the newest 4.x), into build/<name>.css. Usage: node tests/consumer/build.mjs [floor|latest]
// Each directory needs its own `npm ci` first.
import { copyFileSync, mkdirSync, existsSync } from 'node:fs'
import { spawnSync } from 'node:child_process'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const here = dirname(fileURLToPath(import.meta.url))
const names = process.argv.length > 2 ? process.argv.slice(2) : ['floor', 'latest']

mkdirSync(join(here, 'build'), { recursive: true })

for (const name of names) {
  const dir = join(here, name)
  const cli = join(dir, 'node_modules', '@tailwindcss', 'cli', 'dist', 'index.mjs')

  if (!existsSync(cli)) {
    console.error(`${name}: Tailwind CLI not installed, run "npm ci" in ${dir}`)
    process.exit(1)
  }

  copyFileSync(join(here, 'input.css'), join(dir, 'input.css'))

  const out = join(here, 'build', `${name}.css`)
  const run = spawnSync(process.execPath, [cli, '-i', join(dir, 'input.css'), '-o', out], { cwd: dir, stdio: 'inherit' })

  if (run.status !== 0) {
    console.error(`${name}: Tailwind compile failed`)
    process.exit(run.status ?? 1)
  }

  console.log(`${name}: wrote ${out}`)
}
