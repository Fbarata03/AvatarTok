import { useEffect, useState } from 'react'
import { Heart, MessageCircle, Share2, Bookmark, Music2, Sparkles, ChevronRight, Gift, MoreHorizontal } from 'lucide-react'
import api from '../api'
import { Avatar } from '../components/Layout'

const TABS = ['Para Você', 'Seguindo', 'Amigos']

const SUGGESTED = [
  { name: 'Dance Queen',  handle: 'dance_queen',  followers: '2.4M seguidores', following: false },
  { name: 'Comedy King',  handle: 'comedy_king',  followers: '890K seguidores', following: false },
  { name: 'Gamer Pro',    handle: 'gamer_pro',    followers: '1.2M seguidores', following: true },
]
const HOT_SOUNDS = [
  { title: 'Levitating (Remix)', artist: 'Dua Lipa',    videos: '2.4M vídeos', color: '#7c3aed' },
  { title: 'Blinding Lights',    artist: 'The Weeknd',  videos: '1.8M vídeos', color: '#f59e0b' },
  { title: 'Cyberpunk Theme',    artist: 'Synthwave',   videos: '956K vídeos', color: '#06b6d4' },
]
const MOCK_VIDEOS = [
  {
    id: '1', username: 'dance_queen', description: 'Novo desafio de dança com meu avatar! 🎭✨ Quem mais vai tentar?',
    hashtags: ['#AvatarDance', '#Challenge', '#FYP'], sound: 'Levitating — Dua Lipa',
    likes: 24500, comments: 1200, shares: 856, saves: 341,
    grad: 'linear-gradient(160deg, #1a0535 0%, #0d1a40 60%, #0d0722 100%)',
    emoji: '🎭',
  },
  {
    id: '2', username: 'comedy_king', description: 'Quem disse que avatares não têm humor? 😂🤣 Corre que ficou épico!',
    hashtags: ['#AvatarComedy', '#Humor', '#FYP'], sound: 'Som original',
    likes: 8900, comments: 543, shares: 210, saves: 88,
    grad: 'linear-gradient(160deg, #200a0a 0%, #0a1020 60%, #0a0520 100%)',
    emoji: '😂',
  },
  {
    id: '3', username: 'gamer_pro', description: 'Avatar no modo batalha! ⚔️🎮 Esse efeito ficou incrível',
    hashtags: ['#GamingAvatar', '#Battle', '#FYP'], sound: 'Cyberpunk Theme',
    likes: 15600, comments: 920, shares: 440, saves: 222,
    grad: 'linear-gradient(160deg, #0a0a20 0%, #0d1a10 60%, #050d20 100%)',
    emoji: '⚔️',
  },
]

export default function Feed() {
  const [videos, setVideos] = useState(MOCK_VIDEOS)
  const [tab, setTab] = useState(0)

  useEffect(() => {
    api.get('/videos/feed').then(r => {
      const v = r.data.data?.videos || []
      if (v.length > 0) setVideos(v.map((x, i) => ({ ...MOCK_VIDEOS[i % MOCK_VIDEOS.length], ...x })))
    }).catch(() => {})
  }, [])

  return (
    <div style={{ display: 'flex', height: 'calc(100vh - 58px)', overflow: 'hidden' }}>
      {/* ── Feed column ─────────────────────── */}
      <div style={{ flex: 1, overflowY: 'auto' }}>
        {/* Tabs */}
        <div style={{
          display: 'flex', borderBottom: '1px solid var(--border)',
          background: 'var(--bg)', position: 'sticky', top: 0, zIndex: 5,
        }}>
          {TABS.map((t, i) => (
            <button key={t} onClick={() => setTab(i)} style={{
              padding: '14px 22px', background: 'none', fontSize: 14,
              color: tab === i ? 'var(--text)' : 'var(--muted)',
              fontWeight: tab === i ? 700 : 400,
              borderBottom: tab === i ? '2px solid #a78bfa' : '2px solid transparent',
              transition: 'all .15s',
            }}>{t}</button>
          ))}
        </div>

        {/* Videos */}
        <div style={{ padding: '0 24px 40px', maxWidth: 600 }}>
          {videos.map(v => <VideoCard key={v.id} video={v} />)}
        </div>
      </div>

      {/* ── Right sidebar ───────────────────── */}
      <div style={{
        width: 300, flexShrink: 0,
        padding: '20px 20px 20px 4px',
        overflowY: 'auto',
        borderLeft: '1px solid var(--border)',
      }}>
        {/* Suggested creators */}
        <div style={{
          background: 'var(--surface)', border: '1px solid var(--border)',
          borderRadius: 'var(--radius-lg)', padding: '16px', marginBottom: 16,
        }}>
          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 14 }}>
            <span style={{ fontWeight: 700, fontSize: 13 }}>Criadores sugeridos</span>
            <button style={{ color: '#a78bfa', fontSize: 11, fontWeight: 600, background: 'none', display: 'flex', alignItems: 'center', gap: 2 }}>
              Ver todos <ChevronRight size={11} />
            </button>
          </div>
          {SUGGESTED.map(c => (
            <div key={c.handle} style={{ display: 'flex', alignItems: 'center', gap: 9, marginBottom: 12 }}>
              <Avatar name={c.name} size={34} />
              <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ fontWeight: 600, fontSize: 12, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{c.name}</div>
                <div style={{ color: 'var(--muted)', fontSize: 11 }}>@{c.handle} · {c.followers}</div>
              </div>
              <button style={{
                padding: '5px 12px', borderRadius: 20, fontSize: 11, fontWeight: 700, flexShrink: 0,
                background: c.following ? 'var(--surface3)' : 'var(--grad)',
                color: c.following ? 'var(--muted)' : '#fff',
                border: c.following ? '1px solid var(--border)' : 'none',
              }}>
                {c.following ? 'Seguindo' : 'Seguir'}
              </button>
            </div>
          ))}
        </div>

        {/* Trending sounds */}
        <div style={{
          background: 'var(--surface)', border: '1px solid var(--border)',
          borderRadius: 'var(--radius-lg)', padding: '16px',
        }}>
          <div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: 14 }}>
            <span style={{ fontWeight: 700, fontSize: 13 }}>🔥 Sons em alta</span>
            <button style={{ color: '#a78bfa', fontSize: 11, fontWeight: 600, background: 'none', display: 'flex', alignItems: 'center', gap: 2 }}>
              Ver todos <ChevronRight size={11} />
            </button>
          </div>
          {HOT_SOUNDS.map(s => (
            <div key={s.title} style={{ display: 'flex', alignItems: 'center', gap: 10, marginBottom: 12 }}>
              <div style={{
                width: 38, height: 38, borderRadius: 9, flexShrink: 0,
                background: s.color + '30', border: `1px solid ${s.color}50`,
                display: 'flex', alignItems: 'center', justifyContent: 'center',
              }}>
                <Music2 size={16} color={s.color} />
              </div>
              <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ fontWeight: 600, fontSize: 12, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{s.title}</div>
                <div style={{ color: 'var(--muted)', fontSize: 11 }}>{s.artist} · {s.videos}</div>
              </div>
            </div>
          ))}
        </div>
      </div>
    </div>
  )
}

function VideoCard({ video }) {
  const [liked, setLiked] = useState(false)
  const [saved, setSaved] = useState(false)
  const [likes, setLikes] = useState(video.likes || video.like_count || 0)

  let tags = []
  if (Array.isArray(video.hashtags)) {
    tags = video.hashtags
  } else if (typeof video.hashtags === 'string') {
    try {
      tags = JSON.parse(video.hashtags)
    } catch (e) {
      tags = []
    }
  }

  function toggleLike() {
    setLiked(l => !l)
    setLikes(n => liked ? n - 1 : n + 1)
    api[liked ? 'delete' : 'post'](`/videos/${video.id}/like`).catch(() => {})
  }

  return (
    <article style={{ marginTop: 20 }}>
      {/* Video area */}
      <div style={{
        borderRadius: 'var(--radius-lg)', overflow: 'hidden',
        background: video.grad || 'linear-gradient(160deg,#1a0535,#0d1a40)',
        position: 'relative', aspectRatio: '9/16', maxHeight: 540,
        cursor: 'pointer',
      }}>
        {/* Avatar animation */}
        <div style={{
          position: 'absolute', inset: 0,
          display: 'flex', alignItems: 'center', justifyContent: 'center',
        }}>
          <div style={{ textAlign: 'center' }}>
            <div style={{
              width: 90, height: 90, borderRadius: '50%', margin: '0 auto 10px',
              background: 'linear-gradient(135deg, var(--primary), var(--accent))',
              display: 'flex', alignItems: 'center', justifyContent: 'center',
              fontSize: 36, boxShadow: '0 0 60px var(--primary-glow), 0 0 100px var(--accent-glow)',
              animation: 'float 3s ease-in-out infinite',
            }}>
              {video.emoji || <Sparkles size={36} color="#fff" />}
            </div>
          </div>
        </div>

        {/* Action buttons — right edge */}
        <div style={{
          position: 'absolute', right: 10, bottom: 80,
          display: 'flex', flexDirection: 'column', gap: 16, alignItems: 'center',
        }}>
          <ActionBtn icon={<Heart size={22} fill={liked ? '#ef4899' : 'none'} color={liked ? '#ec4899' : '#fff'} />}
            label={fmt(likes)} onClick={toggleLike} active={liked} />
          <ActionBtn icon={<MessageCircle size={22} color="#fff" />} label={fmt(video.comments || 0)} />
          <ActionBtn icon={<Gift size={22} color="#fff" />} label={fmt(video.shares || 0)} />
          <ActionBtn icon={<Bookmark size={22} fill={saved ? '#a78bfa' : 'none'} color={saved ? '#a78bfa' : '#fff'} />}
            label="Salvar" onClick={() => setSaved(s => !s)} />
        </div>

        {/* Bottom info */}
        <div style={{
          position: 'absolute', bottom: 0, left: 0, right: 50,
          padding: '60px 14px 14px',
          background: 'linear-gradient(transparent, rgba(0,0,0,0.75))',
        }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: 8, marginBottom: 6 }}>
            <Avatar name={video.username || 'U'} size={32} ring />
            <span style={{ fontWeight: 700, fontSize: 13, color: '#fff' }}>@{video.username || 'criador'}</span>
            <span style={{
              background: 'rgba(255,255,255,0.15)', color: '#fff', fontSize: 10, fontWeight: 700,
              padding: '2px 8px', borderRadius: 20, border: '1px solid rgba(255,255,255,0.25)',
            }}>✓</span>
          </div>
          <p style={{ color: 'rgba(255,255,255,0.9)', fontSize: 13, lineHeight: 1.4, marginBottom: 6 }}>
            {video.description}
          </p>
          <div style={{ display: 'flex', gap: 6, flexWrap: 'wrap', marginBottom: 8 }}>
            {tags.map(h => (
              <span key={h} style={{ color: 'rgba(255,255,255,0.8)', fontSize: 12, fontWeight: 600 }}>{h}</span>
            ))}
          </div>
          <div style={{ display: 'flex', alignItems: 'center', gap: 6, color: 'rgba(255,255,255,0.7)', fontSize: 12 }}>
            <Music2 size={12} />
            <span>{video.sound || video.sound_title || 'Som original'}</span>
          </div>
        </div>

        {/* More button */}
        <button style={{
          position: 'absolute', top: 12, right: 12,
          background: 'rgba(0,0,0,0.4)', backdropFilter: 'blur(8px)',
          border: 'none', borderRadius: 8, padding: 6, color: '#fff', display: 'flex',
        }}>
          <MoreHorizontal size={16} />
        </button>
      </div>
    </article>
  )
}

function ActionBtn({ icon, label, onClick, active }) {
  return (
    <button onClick={onClick} style={{
      background: 'rgba(0,0,0,0.35)', backdropFilter: 'blur(8px)',
      border: 'none', borderRadius: 12, padding: '8px',
      display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 3,
      cursor: 'pointer', transition: 'transform .15s',
    }}
      onMouseEnter={e => e.currentTarget.style.transform = 'scale(1.08)'}
      onMouseLeave={e => e.currentTarget.style.transform = 'scale(1)'}
    >
      {icon}
      {label && <span style={{ color: '#fff', fontSize: 11, fontWeight: 600 }}>{label}</span>}
    </button>
  )
}

function fmt(n) {
  if (n >= 1e6) return (n / 1e6).toFixed(1) + 'M'
  if (n >= 1e3) return (n / 1e3).toFixed(1) + 'K'
  return String(n)
}
