import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import fs from 'fs'
import path from 'path'

// Copy logos if they exist in the brain folder
const sourceDir = 'C:\\Users\\USER\\.gemini\\antigravity\\brain\\e7a49652-dd12-487b-ad02-62cf1970c050'
const destDir = path.resolve('src/assets')

const filesToCopy = [
  { src: 'logo_gore_piura_hd_1781098599522.png', dest: 'logo-gore-piura.png' },
  { src: 'logo_ugel_piura_hd_1781098618385.png', dest: 'logo-ugel-piura.png' },
  { src: 'logo_siagie_hd_1781098639896.png', dest: 'logo-siagie.png' }
]

if (!fs.existsSync(destDir)) {
  fs.mkdirSync(destDir, { recursive: true })
}

filesToCopy.forEach(file => {
  const srcPath = path.join(sourceDir, file.src)
  const destPath = path.join(destDir, file.dest)
  if (fs.existsSync(srcPath)) {
    try {
      fs.copyFileSync(srcPath, destPath)
      console.log(`Copied logo: ${file.dest}`)
    } catch (err) {
      console.error(`Error copying logo:`, err)
    }
  }
})

// https://vite.dev/config/
export default defineConfig({
  plugins: [react()],
})
