import { useEffect, useState } from 'react'
import { Search, ChevronRight, Music2, Play } from 'lucide-react'
import { Avatar } from '../components/Layout'

const HASHTAGS = [
  { tag: '#AvatarDance', color: '#7c3aed' },
  { tag: '#FaceTracking', color: '#ec4899' },
  { tag: '#AvatarTokChallenge', color: '#10b981' },
  { tag: '#3DAvatar', color: '#3b82f6' },
  { tag: '#VirtualCreator', color: '#f59e0b' },
  { tag: '#MetaverseVibes', color: '#ef4444' },
]
const CREATORS = [
  { name: 'Dance Queen',  handle: 'dance_queen' },
  { name: 'Comedy King',  handle: 'comedy_king' },
  { name: 'Gamer Pro',    handle: 'gamer_pro' },
  { name: 'Art Creator',  handle: 'art_creator' },
  { name: 'Music Star',   handle: 'music_star' },
]
const SOUNDS = [
  { title: 'Levitating (Remix)', artist: 'Dua Lipa',    videos: '2.4M vídeos', duration: '3:24', color: '#7c3aed' },
  { title: 'Blinding Lights',    artist: 'The Weeknd',  videos: '1.8M vídeos', duration: '3:20', color: '#f59e0b' },
  { title: 'Cyberpunk Theme',    artist: 'Synthwave',   videos: '956K vídeos', duration: '4:12', color: '#06b6d4' },
]
const VIDEOS = [
  { grad: 'linear-gradient(160deg,#1a0535,#0d1a40)', views: '2.4M', emoji: '🎭' },
  { grad: 'linear-gradient(160deg,#200a0a,#0a1020)', views: '890K', emoji: '😂' },
  { grad: 'linear-gradient(160deg,#0a1020,#1a0535)', views: '1.2M', emoji: '⚔️' },
  { grad: 'linear-gradient(160deg,#0a1f10,#0d1a40)', views: '567K', emoji: '🌟' },
]

export default function Discover() {
  const [search, setSearch] = useState('')
  const [focused, setFocused] = useState(false)

  return (
    <div style={{ maxWidth: 900, margin: '0 auto', padding: '24px 24px 48px' }}>
      {/* Search */}
      <div style={{
        display: 'flex', alignItems: 'center', gap: 10,
        background: focused ? 'rgba(255, 255, 255, 0.05)' : 'rgba(255, 255, 255, 0.03)',
        border: `1.5px solid ${focused ? 'rgba(124, 58, 237, 0.4)' : 'rgba(255, 255, 255, 0.08)'}`,
        borderRadius: 14, padding: '0 18px', marginBottom: 28,
        transition: 'all 0.25s cubic-bezier(0.4, 0, 0.2, 1)',
        boxShadow: focused ? '0 0 20px rgba(124, 58, 237, 0.15)' : 'none',
      }}>
        <Search size={16} color="var(--text-2)" />
        <input
          value={search} onChange={e => setSearch(e.target.value)}
          onFocus={() => setFocused(true)} onBlur={() => setFocused(false)}
          placeholder="Buscar criadores, vídeos, hashtags..."
          style={{ flex: 1, padding: '14px 0', background: 'none', border: 'none', color: 'var(--text)', outline: 'none', fontSize: 14 }}
        />
      </div>

      {/* Hashtags */}
      <Section title="Hashtags em alta">
        <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
          {HASHTAGS.map(h => (
            <button key={h.tag} style={{
              padding: '7px 16px', borderRadius: 20, fontSize: 13, fontWeight: 600,
              background: h.color + '20', color: h.color,
              border: `1px solid ${h.color}40`, cursor: 'pointer', transition: 'all .15s',
            }}
              onMouseEnter={e => { e.currentTarget.style.background = h.color + '35' }}
              onMouseLeave={e => { e.currentTarget.style.background = h.color + '20' }}
            >
              {h.tag}
            </button>
          ))}
        </div>
      </Section>

      {/* Creators */}
      <Section title="⭐ Criadores para seguir" action="Ver todos —">
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(5, 1fr)', gap: 12 }}>
          {CREATORS.map(c => (
            <div key={c.handle} style={{
              background: 'rgba(255, 255, 255, 0.02)', border: '1px solid rgba(255, 255, 255, 0.06)',
              borderRadius: 'var(--radius-lg)', padding: '18px 12px', textAlign: 'center',
              cursor: 'pointer',
            }} className="glass-interactive"
            >
              <div style={{ display: 'flex', justifyContent: 'center', marginBottom: 10 }}>
                <div style={{ position: 'relative' }}>
                  <Avatar name={c.name} size={56} ring />
                  <div style={{
                    position: 'absolute', inset: -3, borderRadius: '50%',
                    border: '2px solid transparent',
                    background: 'linear-gradient(var(--surface),var(--surface)) padding-box, var(--grad) border-box',
                  }} />
                </div>
              </div>
              <div style={{ fontWeight: 700, fontSize: 13, marginBottom: 2 }}>{c.name}</div>
              <div style={{ color: 'var(--muted)', fontSize: 11, marginBottom: 10 }}>@{c.handle}</div>
              <button style={{
                width: '100%', padding: '7px 0', borderRadius: 20, fontSize: 12, fontWeight: 700,
                background: 'var(--grad)', color: '#fff', boxShadow: '0 2px 8px var(--primary-glow)',
              }}>Seguir</button>
            </div>
          ))}
        </div>
      </Section>

      {/* Sounds trending */}
      <Section title="Sons trending">
        <div style={{ display: 'flex', flexDirection: 'column', gap: 1 }}>
          {SOUNDS.map(s => (
            <div key={s.title} style={{
              display: 'flex', alignItems: 'center', gap: 14,
              padding: '14px 16px', borderRadius: 'var(--radius)',
              background: 'rgba(255, 255, 255, 0.02)', border: '1px solid rgba(255, 255, 255, 0.06)',
              cursor: 'pointer', marginBottom: 6,
            }} className="glass-interactive"
            >
              <div style={{
                width: 46, height: 46, borderRadius: 12, flexShrink: 0,
                background: s.color + '25', border: `1.5px solid ${s.color}50`,
                display: 'flex', alignItems: 'center', justifyContent: 'center',
              }}>
                <Music2 size={20} color={s.color} />
              </div>
              <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ fontWeight: 700, fontSize: 14 }}>{s.title}</div>
                <div style={{ color: 'var(--muted)', fontSize: 12, marginTop: 2 }}>
                  {s.artist} · {s.videos} · {s.duration}
                </div>
              </div>
              <button style={{
                padding: '8px 20px', borderRadius: 20, fontSize: 13, fontWeight: 700,
                background: 'var(--grad)', color: '#fff', flexShrink: 0,
              }}>Usar som</button>
            </div>
          ))}
        </div>
      </Section>

      {/* Popular videos */}
      <Section title="🎬 Vídeos populares">
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(4, 1fr)', gap: 8 }}>
          {VIDEOS.map((v, i) => (
            <div key={i} style={{
              borderRadius: 'var(--radius)', overflow: 'hidden', cursor: 'pointer',
              background: v.grad, position: 'relative', aspectRatio: '9/16',
              transition: 'transform .15s',
            }}
              onMouseEnter={e => e.currentTarget.style.transform = 'translateY(-2px) scale(1.01)'}
              onMouseLeave={e => e.currentTarget.style.transform = 'none'}
            >
              <div style={{
                position: 'absolute', inset: 0,
                display: 'flex', alignItems: 'center', justifyContent: 'center',
                fontSize: 32,
              }}>{v.emoji}</div>
              <div style={{
                position: 'absolute', bottom: 0, left: 0, right: 0,
                padding: '24px 8px 8px',
                background: 'linear-gradient(transparent, rgba(0,0,0,0.65))',
              }}>
                <div style={{ display: 'flex', alignItems: 'center', gap: 4, color: '#fff', fontSize: 12, fontWeight: 700 }}>
                  <Play size={11} fill="#fff" /> {v.views}
                </div>
              </div>
            </div>
          ))}
        </div>
      </Section>
    </div>
  )
}

function Section({ title, action, children }) {
  return (
    <div style={{ marginBottom: 36 }}>
      <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 16 }}>
        <h2 style={{ fontWeight: 800, fontSize: 16 }}>{title}</h2>
        {action && (
          <button style={{ color: '#a78bfa', fontSize: 12, fontWeight: 600, background: 'none', display: 'flex', alignItems: 'center', gap: 2 }}>
            {action} <ChevronRight size={12} />
          </button>
        )}
      </div>
      {children}
    </div>
  )
}
