import { useState } from 'react'
import { Search, Music2, Play, Pause } from 'lucide-react'

const CATEGORIES = [
  { id: 'trending',  label: 'Trending',   color: '#7c3aed' },
  { id: 'original',  label: 'Originais',  color: '#ec4899' },
  { id: 'favorites', label: 'Favoritos',  color: '#ef4444' },
  { id: 'dance',     label: 'Dança',      color: '#f59e0b' },
  { id: 'comedy',    label: 'Comédia',    color: '#10b981' },
  { id: 'gaming',    label: 'Gaming',     color: '#3b82f6' },
  { id: 'art',       label: 'Arte',       color: '#06b6d4' },
]
const SOUNDS = [
  { id: 1, title: 'Levitating (Remix)',      artist: 'Dua Lipa',        videos: '2.4M vídeos', duration: '3:24', color: '#7c3aed' },
  { id: 2, title: 'Blinding Lights',         artist: 'The Weeknd',      videos: '1.8M vídeos', duration: '3:20', color: '#f59e0b' },
  { id: 3, title: 'Cyberpunk Theme',         artist: 'Synthwave',       videos: '956K vídeos', duration: '4:12', color: '#06b6d4' },
  { id: 4, title: 'Avatar Dance Challenge',  artist: '@dance_queen',    videos: '567K vídeos', duration: '0:30', color: '#ec4899' },
  { id: 5, title: 'Neon Nights',             artist: 'Electronic Vibes',videos: '423K vídeos', duration: '2:45', color: '#3b82f6' },
  { id: 6, title: 'Digital Dreams',          artist: 'AI Orchestra',    videos: '312K vídeos', duration: '3:10', color: '#10b981' },
  { id: 7, title: 'Metaverse Funk',          artist: 'VirtualBeats',    videos: '289K vídeos', duration: '2:58', color: '#ef4444' },
]

export default function Sounds() {
  const [cat, setCat] = useState('trending')
  const [search, setSearch] = useState('')
  const [playing, setPlaying] = useState(null)

  const filtered = SOUNDS.filter(s =>
    s.title.toLowerCase().includes(search.toLowerCase()) ||
    s.artist.toLowerCase().includes(search.toLowerCase())
  )

  return (
    <div style={{ maxWidth: 860, margin: '0 auto', padding: '24px 24px 48px' }}>
      <h1 style={{ fontWeight: 800, fontSize: 20, marginBottom: 20, display: 'flex', alignItems: 'center', gap: 8 }}>
        🎵 Biblioteca de Sons
      </h1>

      {/* Search */}
      <div style={{
        display: 'flex', alignItems: 'center', gap: 10,
        background: 'var(--surface)', border: '1px solid var(--border)',
        borderRadius: 12, padding: '0 16px', marginBottom: 20,
      }}>
        <Search size={16} color="var(--muted)" />
        <input
          value={search} onChange={e => setSearch(e.target.value)}
          placeholder="Buscar sons, artistas..."
          style={{ flex: 1, padding: '13px 0', background: 'none', border: 'none', color: 'var(--text)', fontSize: 14, outline: 'none' }}
        />
      </div>

      {/* Category filters */}
      <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap', marginBottom: 24 }}>
        {CATEGORIES.map(c => (
          <button key={c.id} onClick={() => setCat(c.id)} style={{
            padding: '7px 16px', borderRadius: 20, fontSize: 13, fontWeight: 600,
            background: cat === c.id ? c.color : 'var(--surface)',
            color: cat === c.id ? '#fff' : 'var(--muted)',
            border: cat === c.id ? 'none' : '1px solid var(--border)',
            boxShadow: cat === c.id ? `0 2px 12px ${c.color}50` : 'none',
            transition: 'all .15s',
          }}>
            {c.label}
          </button>
        ))}
      </div>

      {/* Sounds list */}
      <div style={{ display: 'flex', flexDirection: 'column', gap: 4 }}>
        {filtered.map(s => (
          <div key={s.id} style={{
            display: 'flex', alignItems: 'center', gap: 14,
            padding: '14px 16px', borderRadius: 'var(--radius)',
            background: 'var(--surface)', border: '1px solid var(--border)',
            cursor: 'pointer', transition: 'border-color .15s',
          }}
            onMouseEnter={e => e.currentTarget.style.borderColor = 'rgba(255,255,255,0.12)'}
            onMouseLeave={e => e.currentTarget.style.borderColor = 'var(--border)'}
          >
            {/* Cover */}
            <div style={{
              width: 48, height: 48, borderRadius: 12, flexShrink: 0, position: 'relative',
              background: s.color + '30', border: `1.5px solid ${s.color}50`,
              display: 'flex', alignItems: 'center', justifyContent: 'center',
              cursor: 'pointer',
            }} onClick={() => setPlaying(playing === s.id ? null : s.id)}>
              {playing === s.id
                ? <Pause size={20} color={s.color} />
                : <Music2 size={20} color={s.color} />}
            </div>

            {/* Info */}
            <div style={{ flex: 1, minWidth: 0 }}>
              <div style={{ fontWeight: 700, fontSize: 14, marginBottom: 3 }}>{s.title}</div>
              <div style={{ color: 'var(--muted)', fontSize: 12 }}>
                {s.artist} · {s.videos} · {s.duration}
              </div>
            </div>

            {/* Play indicator */}
            {playing === s.id && (
              <div style={{ display: 'flex', gap: 2, alignItems: 'center', marginRight: 8 }}>
                {[1,2,3,4].map(b => (
                  <div key={b} style={{
                    width: 3, borderRadius: 3, background: s.color,
                    height: `${8 + (b % 3) * 6}px`,
                    animation: `pulse-dot ${0.4 + b * 0.1}s ease-in-out infinite alternate`,
                  }} />
                ))}
              </div>
            )}

            {/* Use button */}
            <button style={{
              padding: '8px 18px', borderRadius: 20, fontSize: 13, fontWeight: 700, flexShrink: 0,
              background: 'var(--grad)', color: '#fff',
              boxShadow: '0 2px 10px var(--primary-glow)',
            }}>
              Usar som
            </button>
          </div>
        ))}
      </div>
    </div>
  )
}
