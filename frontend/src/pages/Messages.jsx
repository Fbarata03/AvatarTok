import { useState } from 'react'
import { Search, Send, Smile, Image, Phone } from 'lucide-react'
import { Avatar } from '../components/Layout'

const CONVOS = [
  { id: 1, name: 'Dance Queen',  handle: 'dance_queen', last: 'Adorei seu último vídeo! 🔥',  time: '2min',  unread: 2, online: true },
  { id: 2, name: 'Comedy King',  handle: 'comedy_king', last: 'Vamos fazer um dueto? 😄',      time: '15min', unread: 0, online: true },
  { id: 3, name: 'Gamer Pro',    handle: 'gamer_pro',   last: 'GG! That was insane 🎮',        time: '1h',    unread: 0 },
  { id: 4, name: 'Art Creator',  handle: 'art_creator', last: 'Obrigada pelo presente! 🎁',    time: '3h',    unread: 0 },
]
const MOCK_MSGS = [
  { id: 1, me: false, text: 'Oi! Tudo bem? 😊',                                  time: '14:30' },
  { id: 2, me: true,  text: 'Oi! Tudo ótimo e contigo?',                          time: '14:31' },
  { id: 3, me: false, text: 'Adorei seu último vídeo! O avatar ficou incrível',   time: '14:32' },
  { id: 4, me: true,  text: 'Obrigado! 😊 Usei o novo efeito Neon Glow',          time: '14:33' },
  { id: 5, me: false, text: 'Que legal! Me ensina como usar? 💜',                 time: '14:35' },
  { id: 6, me: true,  text: 'Claro! Vou te mandar um tutorial! 🚀',               time: '14:36' },
]

export default function Messages() {
  const [active, setActive] = useState(CONVOS[0])
  const [input, setInput] = useState('')
  const [msgs, setMsgs] = useState(MOCK_MSGS)

  function send() {
    if (!input.trim()) return
    setMsgs(m => [...m, { id: Date.now(), me: true, text: input, time: new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }) }])
    setInput('')
  }

  return (
    <div style={{ display: 'flex', height: 'calc(100vh - 58px)', overflow: 'hidden' }}>
      {/* ── Conversations list ──────────────── */}
      <div style={{ width: 300, flexShrink: 0, borderRight: '1px solid var(--border)', display: 'flex', flexDirection: 'column' }}>
        <div style={{ padding: '16px 14px 12px' }}>
          <h2 style={{ fontWeight: 800, fontSize: 16, marginBottom: 12 }}>Mensagens</h2>
          <div style={{
            display: 'flex', alignItems: 'center', gap: 8,
            background: 'var(--surface2)', border: '1px solid var(--border)',
            borderRadius: 10, padding: '0 12px',
          }}>
            <Search size={14} color="var(--muted)" />
            <input placeholder="Buscar conversas..." style={{ flex: 1, background: 'none', border: 'none', color: 'var(--text)', fontSize: 13, padding: '9px 0', outline: 'none' }} />
          </div>
        </div>

        <div style={{ flex: 1, overflowY: 'auto' }}>
          {CONVOS.map(c => (
            <div key={c.id} onClick={() => setActive(c)} style={{
              display: 'flex', alignItems: 'center', gap: 10,
              padding: '12px 14px', cursor: 'pointer',
              background: active.id === c.id ? 'rgba(124,58,237,0.1)' : 'transparent',
              borderLeft: active.id === c.id ? '2px solid var(--primary)' : '2px solid transparent',
              transition: 'all .12s',
            }}
              onMouseEnter={e => { if (active.id !== c.id) e.currentTarget.style.background = 'rgba(255,255,255,0.03)' }}
              onMouseLeave={e => { if (active.id !== c.id) e.currentTarget.style.background = 'transparent' }}
            >
              <div style={{ position: 'relative', flexShrink: 0 }}>
                <Avatar name={c.name} size={40} />
                {c.online && (
                  <div style={{
                    position: 'absolute', bottom: 1, right: 1,
                    width: 10, height: 10, borderRadius: '50%',
                    background: '#10b981', border: '2px solid var(--bg)',
                  }} />
                )}
              </div>
              <div style={{ flex: 1, minWidth: 0 }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 3 }}>
                  <span style={{ fontWeight: 700, fontSize: 13 }}>{c.name}</span>
                  <span style={{ color: 'var(--muted)', fontSize: 11 }}>{c.time}</span>
                </div>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                  <span style={{ color: 'var(--muted)', fontSize: 12, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap', flex: 1 }}>
                    {c.last}
                  </span>
                  {c.unread > 0 && (
                    <span style={{
                      background: '#ef4444', color: '#fff', fontSize: 10, fontWeight: 700,
                      minWidth: 18, height: 18, borderRadius: 9,
                      display: 'flex', alignItems: 'center', justifyContent: 'center',
                      flexShrink: 0, marginLeft: 6,
                    }}>{c.unread}</span>
                  )}
                </div>
              </div>
            </div>
          ))}
        </div>
      </div>

      {/* ── Chat area ───────────────────────── */}
      <div style={{ flex: 1, display: 'flex', flexDirection: 'column', overflow: 'hidden' }}>
        {/* Chat header */}
        <div style={{
          padding: '14px 20px', borderBottom: '1px solid var(--border)',
          display: 'flex', alignItems: 'center', gap: 12, flexShrink: 0,
        }}>
          <Avatar name={active.name} size={36} />
          <div style={{ flex: 1 }}>
            <div style={{ fontWeight: 700, fontSize: 14 }}>{active.name}</div>
            {active.online && (
              <div style={{ display: 'flex', alignItems: 'center', gap: 5, color: '#10b981', fontSize: 12 }}>
                <div style={{ width: 6, height: 6, borderRadius: '50%', background: '#10b981' }} />
                Online agora
              </div>
            )}
          </div>
          <button style={{ background: 'none', color: 'var(--muted)', padding: 6, borderRadius: 8, display: 'flex' }}>
            <Phone size={18} />
          </button>
        </div>

        {/* Messages */}
        <div style={{ flex: 1, overflowY: 'auto', padding: '20px' }}>
          {msgs.map(m => (
            <div key={m.id} style={{
              display: 'flex', justifyContent: m.me ? 'flex-end' : 'flex-start',
              marginBottom: 14,
            }}>
              {!m.me && <Avatar name={active.name} size={28} style={{ marginRight: 8, flexShrink: 0 }} />}
              <div>
                <div style={{
                  padding: '10px 14px', borderRadius: m.me ? '16px 16px 4px 16px' : '16px 16px 16px 4px',
                  background: m.me ? 'linear-gradient(135deg, #7c3aed, #ec4899)' : 'var(--surface2)',
                  color: m.me ? '#fff' : 'var(--text)',
                  fontSize: 14, lineHeight: 1.4, maxWidth: 340,
                  border: m.me ? 'none' : '1px solid var(--border)',
                }}>
                  {m.text}
                </div>
                <div style={{ color: 'var(--muted)', fontSize: 11, marginTop: 4, textAlign: m.me ? 'right' : 'left' }}>
                  {m.time}
                </div>
              </div>
            </div>
          ))}
        </div>

        {/* Input */}
        <div style={{
          padding: '14px 20px', borderTop: '1px solid var(--border)',
          display: 'flex', alignItems: 'center', gap: 10, flexShrink: 0,
        }}>
          <button style={{ background: 'none', color: 'var(--muted)', padding: 6, display: 'flex' }}>
            <Smile size={20} />
          </button>
          <button style={{ background: 'none', color: 'var(--muted)', padding: 6, display: 'flex' }}>
            <Image size={20} />
          </button>
          <input
            value={input} onChange={e => setInput(e.target.value)}
            onKeyDown={e => e.key === 'Enter' && send()}
            placeholder="Digite uma mensagem..."
            style={{
              flex: 1, background: 'var(--surface2)', border: '1px solid var(--border)',
              borderRadius: 24, padding: '10px 16px', color: 'var(--text)', fontSize: 14, outline: 'none',
            }}
          />
          <button onClick={send} style={{
            width: 40, height: 40, borderRadius: '50%',
            background: 'var(--grad)', color: '#fff',
            display: 'flex', alignItems: 'center', justifyContent: 'center', flexShrink: 0,
            boxShadow: '0 2px 10px var(--primary-glow)',
          }}>
            <Send size={16} />
          </button>
        </div>
      </div>
    </div>
  )
}
