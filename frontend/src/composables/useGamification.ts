import { reactive, computed, watch } from 'vue'

export interface Achievement {
  id: string
  icon: string
  title: string
  description: string
  unlockedAt: string | null
  secret?: boolean
  xpReward: number
}

export interface GamificationState {
  xp: number
  totalFieldsFilled: number
  totalUploads: number
  totalSaves: number
  achievements: Achievement[]
  recentUnlocks: Achievement[]
  showLevelUp: boolean
  previousLevel: number
}

const LEVELS = [
  { level: 1, xpRequired: 0, title: 'Neuling', color: '#9CA3AF' },
  { level: 2, xpRequired: 50, title: 'Anfänger', color: '#60A5FA' },
  { level: 3, xpRequired: 150, title: 'Fortgeschritten', color: '#34D399' },
  { level: 4, xpRequired: 300, title: 'Profi', color: '#FBBF24' },
  { level: 5, xpRequired: 500, title: 'Experte', color: '#F97316' },
  { level: 6, xpRequired: 800, title: 'Meister', color: '#EF4444' },
  { level: 7, xpRequired: 1200, title: 'Legende', color: '#A855F7' },
  { level: 8, xpRequired: 2000, title: 'Formular-Gott', color: '#EC4899' },
]

const defaultAchievements: Achievement[] = [
  { id: 'first_field', icon: '✏️', title: 'Erster Schritt', description: 'Erstes Feld ausgefüllt', unlockedAt: null, xpReward: 20 },
  { id: 'five_fields', icon: '📝', title: 'Fleißig', description: '5 Felder ausgefüllt', unlockedAt: null, xpReward: 50 },
  { id: 'ten_fields', icon: '🔥', title: 'Auf Feuer', description: '10 Felder ausgefüllt', unlockedAt: null, xpReward: 100 },
  { id: 'twenty_fields', icon: '⚡', title: 'Unstoppbar', description: '20 Felder ausgefüllt', unlockedAt: null, xpReward: 200 },
  { id: 'first_upload', icon: '📎', title: 'Uploader', description: 'Erste Datei hochgeladen', unlockedAt: null, xpReward: 30 },
  { id: 'five_uploads', icon: '📦', title: 'Sammelwütig', description: '5 Dateien hochgeladen', unlockedAt: null, xpReward: 75 },
  { id: 'first_save', icon: '💾', title: 'Sicherheitsbewusst', description: 'Erstes Mal gespeichert', unlockedAt: null, xpReward: 15 },
  { id: 'ten_saves', icon: '🛡️', title: 'Backup-König', description: '10 Mal gespeichert', unlockedAt: null, xpReward: 80 },
  { id: 'half_done', icon: '🏔️', title: 'Halbzeit', description: '50% aller Felder ausgefüllt', unlockedAt: null, xpReward: 60 },
  { id: 'all_required', icon: '✅', title: 'Pflichtbewusst', description: 'Alle Pflichtfelder erledigt', unlockedAt: null, xpReward: 150 },
  { id: 'full_complete', icon: '🏆', title: 'Perfektionist', description: '100% aller Felder ausgefüllt', unlockedAt: null, xpReward: 300 },
  { id: 'released', icon: '🚀', title: 'Abgeschossen!', description: 'Formular freigegeben', unlockedAt: null, xpReward: 500 },
  { id: 'speed_demon', icon: '⏱️', title: 'Speed Demon', description: '3 Felder in 30 Sekunden', unlockedAt: null, secret: true, xpReward: 100 },
  { id: 'night_owl', icon: '🦉', title: 'Nachteule', description: 'Nach 22 Uhr gearbeitet', unlockedAt: null, secret: true, xpReward: 50 },
  { id: 'early_bird', icon: '🐦', title: 'Frühaufsteher', description: 'Vor 7 Uhr gearbeitet', unlockedAt: null, secret: true, xpReward: 50 },
]

const STORAGE_KEY = 'formbuilder_gamification'

function loadState(): GamificationState {
  try {
    const raw = localStorage.getItem(STORAGE_KEY)
    if (raw) {
      const saved = JSON.parse(raw)
      // Merge with defaults to add new achievements
      const achievementMap = new Map(saved.achievements?.map((a: Achievement) => [a.id, a]) || [])
      const achievements = defaultAchievements.map(da => {
        const saved = achievementMap.get(da.id) as Achievement | undefined
        return saved ? { ...da, unlockedAt: saved.unlockedAt } : { ...da }
      })
      return { ...saved, achievements, recentUnlocks: [], showLevelUp: false, previousLevel: 0 }
    }
  } catch {}
  return {
    xp: 0,
    totalFieldsFilled: 0,
    totalUploads: 0,
    totalSaves: 0,
    achievements: defaultAchievements.map(a => ({ ...a })),
    recentUnlocks: [],
    showLevelUp: false,
    previousLevel: 0,
  }
}

const state = reactive<GamificationState>(loadState())

// Auto-save
let saveTimer: ReturnType<typeof setTimeout> | null = null
function persistState() {
  if (saveTimer) clearTimeout(saveTimer)
  saveTimer = setTimeout(() => {
    localStorage.setItem(STORAGE_KEY, JSON.stringify({
      xp: state.xp,
      totalFieldsFilled: state.totalFieldsFilled,
      totalUploads: state.totalUploads,
      totalSaves: state.totalSaves,
      achievements: state.achievements,
    }))
  }, 300)
}

// Speed tracking
let recentFieldTimes: number[] = []

function addXP(amount: number) {
  const oldLevel = currentLevel.value.level
  state.previousLevel = oldLevel
  state.xp += amount
  persistState()
  const newLevel = currentLevel.value.level
  if (newLevel > oldLevel) {
    state.showLevelUp = true
    setTimeout(() => { state.showLevelUp = false }, 4000)
  }
}

function unlockAchievement(id: string): boolean {
  const achievement = state.achievements.find(a => a.id === id)
  if (!achievement || achievement.unlockedAt) return false
  achievement.unlockedAt = new Date().toISOString()
  state.recentUnlocks.push({ ...achievement })
  addXP(achievement.xpReward)
  persistState()
  // Auto-clear recent after 5s
  setTimeout(() => {
    const idx = state.recentUnlocks.findIndex(a => a.id === id)
    if (idx >= 0) state.recentUnlocks.splice(idx, 1)
  }, 5000)
  return true
}

function checkTimeAchievements() {
  const hour = new Date().getHours()
  if (hour >= 22 || hour < 5) unlockAchievement('night_owl')
  if (hour >= 5 && hour < 7) unlockAchievement('early_bird')
}

const currentLevel = computed(() => {
  let lvl = LEVELS[0]
  for (const l of LEVELS) {
    if (state.xp >= l.xpRequired) lvl = l
  }
  return lvl
})

const nextLevel = computed(() => {
  const idx = LEVELS.findIndex(l => l.level === currentLevel.value.level)
  return LEVELS[idx + 1] || null
})

const xpProgress = computed(() => {
  if (!nextLevel.value) return 100
  const currentMin = currentLevel.value.xpRequired
  const nextMin = nextLevel.value.xpRequired
  return Math.round(((state.xp - currentMin) / (nextMin - currentMin)) * 100)
})

const unlockedCount = computed(() => state.achievements.filter(a => a.unlockedAt).length)

const motivationalMessages = [
  'Weiter so! Du bist auf einem guten Weg! 💪',
  'Fantastisch! Noch ein paar Felder! 🎯',
  'Du rockst das! 🎸',
  'Fast geschafft, gib nicht auf! 🏁',
  'Beeindruckend, mach weiter! ⭐',
  'Jedes Feld zählt! Du schaffst das! 🌟',
]

function getMotivation(): string {
  return motivationalMessages[Math.floor(Math.random() * motivationalMessages.length)]
}

export function useGamification() {
  return {
    state,
    currentLevel,
    nextLevel,
    xpProgress,
    unlockedCount,
    LEVELS,

    trackFieldFilled() {
      state.totalFieldsFilled++
      checkTimeAchievements()

      // Speed tracking
      const now = Date.now()
      recentFieldTimes.push(now)
      recentFieldTimes = recentFieldTimes.filter(t => now - t < 30000)
      if (recentFieldTimes.length >= 3) unlockAchievement('speed_demon')

      if (state.totalFieldsFilled >= 1) unlockAchievement('first_field')
      if (state.totalFieldsFilled >= 5) unlockAchievement('five_fields')
      if (state.totalFieldsFilled >= 10) unlockAchievement('ten_fields')
      if (state.totalFieldsFilled >= 20) unlockAchievement('twenty_fields')

      addXP(10)
      persistState()
    },

    trackUpload() {
      state.totalUploads++
      if (state.totalUploads >= 1) unlockAchievement('first_upload')
      if (state.totalUploads >= 5) unlockAchievement('five_uploads')
      addXP(15)
      persistState()
    },

    trackSave() {
      state.totalSaves++
      if (state.totalSaves >= 1) unlockAchievement('first_save')
      if (state.totalSaves >= 10) unlockAchievement('ten_saves')
      addXP(5)
      persistState()
    },

    trackCompletion(percent: number, requiredPercent: number) {
      if (percent >= 50) unlockAchievement('half_done')
      if (requiredPercent >= 100) unlockAchievement('all_required')
      if (percent >= 100) unlockAchievement('full_complete')
    },

    trackRelease() {
      unlockAchievement('released')
    },

    getMotivation,
    unlockAchievement,
  }
}
