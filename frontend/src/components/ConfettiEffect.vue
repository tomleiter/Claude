<template>
  <Teleport to="body">
    <canvas
      v-if="active"
      ref="canvasEl"
      class="fixed inset-0 pointer-events-none z-[9999]"
      :width="width"
      :height="height"
    />
  </Teleport>
</template>

<script setup lang="ts">
import { ref, watch, onUnmounted, nextTick } from 'vue'

const props = defineProps<{
  active: boolean
}>()

const canvasEl = ref<HTMLCanvasElement | null>(null)
const width = ref(window.innerWidth)
const height = ref(window.innerHeight)

interface Particle {
  x: number
  y: number
  vx: number
  vy: number
  color: string
  size: number
  rotation: number
  rotationSpeed: number
  shape: 'rect' | 'circle' | 'star'
  opacity: number
  gravity: number
}

const colors = [
  '#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FFEAA7',
  '#DDA0DD', '#98D8C8', '#F7DC6F', '#BB8FCE', '#85C1E9',
  '#F1948A', '#82E0AA', '#F8C471', '#D7BDE2', '#A3E4D7',
]

let particles: Particle[] = []
let animFrame: number | null = null

function createParticles(): Particle[] {
  const p: Particle[] = []
  for (let i = 0; i < 150; i++) {
    const angle = Math.random() * Math.PI * 2
    const velocity = 8 + Math.random() * 12
    p.push({
      x: width.value / 2 + (Math.random() - 0.5) * 200,
      y: height.value / 2 - 100,
      vx: Math.cos(angle) * velocity,
      vy: Math.sin(angle) * velocity - 8,
      color: colors[Math.floor(Math.random() * colors.length)],
      size: 4 + Math.random() * 8,
      rotation: Math.random() * 360,
      rotationSpeed: (Math.random() - 0.5) * 15,
      shape: (['rect', 'circle', 'star'] as const)[Math.floor(Math.random() * 3)],
      opacity: 1,
      gravity: 0.15 + Math.random() * 0.1,
    })
  }
  return p
}

function drawStar(ctx: CanvasRenderingContext2D, cx: number, cy: number, size: number) {
  const spikes = 5
  const outerRadius = size
  const innerRadius = size / 2
  let rot = (Math.PI / 2) * 3
  const step = Math.PI / spikes

  ctx.beginPath()
  ctx.moveTo(cx, cy - outerRadius)
  for (let i = 0; i < spikes; i++) {
    ctx.lineTo(cx + Math.cos(rot) * outerRadius, cy + Math.sin(rot) * outerRadius)
    rot += step
    ctx.lineTo(cx + Math.cos(rot) * innerRadius, cy + Math.sin(rot) * innerRadius)
    rot += step
  }
  ctx.lineTo(cx, cy - outerRadius)
  ctx.closePath()
  ctx.fill()
}

function animate() {
  const canvas = canvasEl.value
  if (!canvas) return
  const ctx = canvas.getContext('2d')
  if (!ctx) return

  ctx.clearRect(0, 0, width.value, height.value)

  let alive = false
  for (const p of particles) {
    p.x += p.vx
    p.y += p.vy
    p.vy += p.gravity
    p.vx *= 0.99
    p.rotation += p.rotationSpeed
    p.opacity -= 0.005

    if (p.opacity <= 0) continue
    alive = true

    ctx.save()
    ctx.translate(p.x, p.y)
    ctx.rotate((p.rotation * Math.PI) / 180)
    ctx.globalAlpha = Math.max(0, p.opacity)
    ctx.fillStyle = p.color

    if (p.shape === 'rect') {
      ctx.fillRect(-p.size / 2, -p.size / 2, p.size, p.size * 0.6)
    } else if (p.shape === 'circle') {
      ctx.beginPath()
      ctx.arc(0, 0, p.size / 2, 0, Math.PI * 2)
      ctx.fill()
    } else {
      drawStar(ctx, 0, 0, p.size / 2)
    }
    ctx.restore()
  }

  if (alive) {
    animFrame = requestAnimationFrame(animate)
  }
}

watch(() => props.active, async (val) => {
  if (val) {
    width.value = window.innerWidth
    height.value = window.innerHeight
    await nextTick()
    particles = createParticles()
    if (animFrame) cancelAnimationFrame(animFrame)
    animFrame = requestAnimationFrame(animate)
  }
})

onUnmounted(() => {
  if (animFrame) cancelAnimationFrame(animFrame)
})
</script>
