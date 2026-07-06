import { useState } from 'react'
import { Sparkles } from 'lucide-react'

const CATEGORIES = [
  { id: 'all',    label: 'Todos',          color: '#7c3aed' },
  { id: 'avatar', label: 'Avatar Filters', color: '#ec4899' },
  { id: 'ar',     label: 'AR',            color: '#3b82f6' },
  { id: 'distort',label: 'Distorção',     color: '#f59e0b' },
  { id: 'beauty', label: 'Beleza',        color: '#10b981' },
  { id: 'trend',  label: 'Trending',      color: '#ef4444' },
]

const EFFECTS = [
  { id: 1, name: 'Neon Glow',      category: 'avatar', isNew: true,  emoji: '✨', grad: 'linear-gradient(160deg,#1a0040,#0d0060,#200050)', glow: '#7c3aed' },
  { id: 2, name: 'Cyberpunk',      category: 'avatar', isNew: false, emoji: '⚡', grad: 'linear-gradient(160deg,#0d0040,#001050,#0a0060)', glow: '#3b82f6' },
  { id: 3, name: 'Surpreso 3D',    category: 'avatar', isNew: true,  emoji: '😮', grad: 'linear-gradient(160deg,#200040,#300050,#100040)', glow: '#ec4899' },
  { id: 4, name: 'Holo Aura',      category: 'ar',    isNew: false, emoji: '🌀', grad: 'linear-gradient(160deg,#001840,#002050,#001030)', glow: '#06b6d4' },
  { id: 5, name: 'Glitch Wave',    category: 'distort',isNew: false, emoji: '📡', grad: 'linear-gradient(160deg,#200010,#300020,#100020)', glow: '#ef4444' },
  { id: 6, name: 'Soft Beauty',    category: 'beauty', isNew: false, emoji: '💐', grad: 'linear-gradient(160deg,#001030,#002040,#000820)', glow: '#10b981' },
  { id: 7, name: 'Fire Avatar',    category: 'avatar', isNew: true,  emoji: '🔥', grad: 'linear-gradient(160deg,#400010,#300020,#200010)', glow: '#f59e0b' },
  { id: 8, name: 'Crystal Echo',   category: 'ar',    isNew: false, emoji: '💎', grad: 'linear-gradient(160deg,#001840,#001040,#002020)', glow: '#06b6d4' },
  { id: 9, name: 'Pixel Storm',    category: 'distort',isNew: false, emoji: '🌪️', grad: 'linear-gradient(160deg,#200040,#100050,#000040)', glow: '#a855f7' },
]

export default function Effects() {
  const [cat, setCat] = useState('all')

  const shown = cat === 'all' ? EFFECTS : EFFECTS.filter(e => e.category === cat)

  return (
    <div style={{ padding: '24px 24px 48px' }}>
      <h1 style={{ fontWeight: 800, fontSize: 20, marginBottom: 20, display: 'flex', alignItems: 'center', gap: 8 }}>
        ✨ Efeitos & Filtros
      </h1>

      {/* Category filters */}
      <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginBottom: 24 }}>
        {CATEGORIES.map(c => (
          <button key={c.id} onClick={() => setCat(c.id)} style={{
            padding: '7px 18px', borderRadius: 20, fontSize: 13, fontWeight: 600,
            background: cat === c.id ? c.color : 'var(--surface)',
            color: cat === c.id ? '#fff' : 'var(--muted)',
            border: cat === c.id ? 'none' : '1px solid var(--border)',
            boxShadow: cat === c.id ? `0 2px 12px ${c.color}55` : 'none',
            transition: 'all .15s',
          }}>
            {c.label}
          </button>
        ))}
      </div>

      {/* Effects grid */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(280px, 1fr))', gap: 14 }}>
        {shown.map(ef => <EffectCard key={ef.id} effect={ef} />)}
      </div>
    </div>
  )
}

function EffectCard({ effect: ef }) {
  const [hovered, setHovered] = useState(false)

  return (
    <div style={{
      borderRadius: 'var(--radius-lg)', overflow: 'hidden', cursor: 'pointer',
      background: ef.grad, position: 'relative', aspectRatio: '3/4',
      transform: hovered ? 'translateY(-4px)' : 'none',
      boxShadow: hovered ? `0 16px 48px rgba(0,0,0,0.5), 0 0 40px ${ef.glow}40` : '0 4px 20px rgba(0,0,0,0.3)',
      transition: 'all .2s',
    }}
      onMouseEnter={() => setHovered(true)}
      onMouseLeave={() => setHovered(false)}
    >
      {/* Radial glow */}
      <div style={{
        position: 'absolute', inset: 0,
        background: `radial-gradient(circle at 50% 40%, ${ef.glow}60, transparent 65%)`,
        opacity: hovered ? 0.9 : 0.55, transition: 'opacity .2s',
      }} />

      {/* Emoji / effect preview */}
      <div style={{
        position: 'absolute', inset: 0,
        display: 'flex', alignItems: 'center', justifyContent: 'center',
        fontSize: 64,
      }}>{ef.emoji}</div>

      {/* NEW badge */}
      {ef.isNew && (
        <div style={{
          position: 'absolute', top: 12, left: 12,
          background: 'var(--grad)', color: '#fff',
          fontSize: 10, fontWeight: 800, padding: '3px 10px', borderRadius: 20,
          letterSpacing: '0.5px',
        }}>NOVO</div>
      )}

      {/* Bottom info */}
      <div style={{
        position: 'absolute', bottom: 0, left: 0, right: 0,
        padding: '48px 14px 14px',
        background: 'linear-gradient(transparent, rgba(0,0,0,0.75))',
      }}>
        <div style={{ fontWeight: 800, fontSize: 15, color: '#fff', marginBottom: 4 }}>{ef.name}</div>

        <button style={{
          width: '100%', padding: '9px 0', borderRadius: 20,
          background: hovered ? 'var(--grad)' : 'rgba(255,255,255,0.15)',
          border: hovered ? 'none' : '1px solid rgba(255,255,255,0.25)',
          color: '#fff', fontWeight: 700, fontSize: 13,
          backdropFilter: 'blur(8px)',
          transition: 'all .2s',
          boxShadow: hovered ? '0 3px 14px var(--primary-glow)' : 'none',
        }}>
          {hovered ? 'Experimentar' : <><Sparkles size={13} style={{ display: 'inline', marginRight: 5 }} />Explorar</>}
        </button>
      </div>
    </div>
  )
}
