import { useState } from 'react'
import { Sparkles, Save, Camera } from 'lucide-react'

const TABS = ['Rosto', 'Cabelo', 'Pele', 'Olhos', 'Boca', 'Acessórios']
const FACE_SHAPES = ['😊', '😐', '🥰', '😎']
const SKIN_COLORS = ['#f5d0a9', '#e8b88a', '#c8874e', '#a0522d', '#7d3a20', '#4a2511']
const HAIR_STYLES  = [
  { bg: 'linear-gradient(135deg,#7c3aed,#ec4899)', label: '1' },
  { bg: 'linear-gradient(135deg,#ec4899,#f59e0b)', label: '2' },
  { bg: 'linear-gradient(135deg,#3b82f6,#06b6d4)', label: '3' },
  { bg: 'linear-gradient(135deg,#10b981,#3b82f6)', label: '4' },
  { bg: 'linear-gradient(135deg,#f59e0b,#ef4444)', label: '5' },
  { bg: 'linear-gradient(135deg,#111,#333)',        label: '6' },
]
const EXPRESSIONS = ['😊', '😄', '😢', '😠']

export default function MyAvatar() {
  const [tab, setTab] = useState(0)
  const [skin, setSkin] = useState(2)
  const [hair, setHair] = useState(0)
  const [face, setFace] = useState(0)
  const [expr, setExpr] = useState(0)

  return (
    <div style={{ display: 'flex', height: 'calc(100vh - 58px)', overflow: 'hidden' }}>
      {/* ── Canvas ─────────────────────────── */}
      <div style={{
        flex: 1, position: 'relative',
        background: 'radial-gradient(ellipse at 50% 40%, #1a0040 0%, #08001a 60%, #050010 100%)',
        display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center',
      }}>
        {/* Purple glow floor */}
        <div style={{
          position: 'absolute', bottom: '15%', left: '50%', transform: 'translateX(-50%)',
          width: 300, height: 80, borderRadius: '50%',
          background: 'rgba(124,58,237,0.25)', filter: 'blur(30px)',
        }} />

        {/* Avatar preview */}
        <div style={{
          width: 180, height: 220, borderRadius: 20,
          background: 'linear-gradient(160deg, #1a0535, #0d1040, #0a0020)',
          border: '1px solid rgba(124,58,237,0.4)',
          display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center',
          gap: 12, boxShadow: '0 0 60px rgba(124,58,237,0.3)',
          position: 'relative',
        }}>
          {/* Face */}
          <div style={{
            width: 80, height: 80, borderRadius: '50%',
            background: SKIN_COLORS[skin],
            display: 'flex', alignItems: 'center', justifyContent: 'center',
            fontSize: 36, boxShadow: `0 0 30px ${SKIN_COLORS[skin]}80`,
            border: `3px solid ${HAIR_STYLES[hair].bg.includes('#7c3aed') ? 'rgba(124,58,237,0.6)' : 'rgba(236,72,153,0.5)'}`,
          }}>
            {EXPRESSIONS[expr]}
          </div>
          {/* Body */}
          <div style={{
            width: 60, height: 70, borderRadius: '40% 40% 30% 30%',
            background: 'linear-gradient(180deg, #7c3aed, #4c1d95)',
            boxShadow: '0 0 20px rgba(124,58,237,0.4)',
          }} />

          {/* Glow ring */}
          <div style={{
            position: 'absolute', inset: -8, borderRadius: 28,
            border: '1px solid rgba(124,58,237,0.3)',
            pointerEvents: 'none',
          }} />
        </div>

        {/* Expression selector */}
        <div style={{ display: 'flex', gap: 12, marginTop: 28 }}>
          {EXPRESSIONS.map((e, i) => (
            <button key={i} onClick={() => setExpr(i)} style={{
              width: 48, height: 48, borderRadius: '50%', fontSize: 22,
              background: expr === i ? 'rgba(124,58,237,0.3)' : 'rgba(255,255,255,0.06)',
              border: `2px solid ${expr === i ? '#a78bfa' : 'transparent'}`,
              transition: 'all .15s',
            }}>{e}</button>
          ))}
        </div>

        {/* Bottom label */}
        <div style={{ position: 'absolute', bottom: 16, color: 'rgba(255,255,255,0.3)', fontSize: 12 }}>
          Arraste para rotacionar o avatar
        </div>
      </div>

      {/* ── Customization panel ─────────────── */}
      <div style={{
        width: 280, flexShrink: 0,
        borderLeft: '1px solid var(--border)',
        background: 'var(--surface)',
        display: 'flex', flexDirection: 'column',
        overflow: 'hidden',
      }}>
        {/* Tabs */}
        <div style={{ display: 'flex', flexWrap: 'wrap', gap: 4, padding: '14px 12px 0', flexShrink: 0 }}>
          {TABS.map((t, i) => (
            <button key={t} onClick={() => setTab(i)} style={{
              padding: '6px 12px', borderRadius: 20, fontSize: 12, fontWeight: 600,
              background: tab === i ? 'var(--grad)' : 'var(--surface2)',
              color: tab === i ? '#fff' : 'var(--muted)',
              border: tab === i ? 'none' : '1px solid var(--border)',
              transition: 'all .15s',
            }}>{t}</button>
          ))}
        </div>

        <div style={{ flex: 1, overflowY: 'auto', padding: '20px 14px' }}>
          {tab === 0 && (
            <>
              <Section label="Formato do rosto">
                <div style={{ display: 'flex', gap: 8 }}>
                  {FACE_SHAPES.map((f, i) => (
                    <button key={i} onClick={() => setFace(i)} style={{
                      width: 52, height: 52, borderRadius: 12, fontSize: 22,
                      background: face === i ? 'rgba(124,58,237,0.25)' : 'var(--surface2)',
                      border: `2px solid ${face === i ? '#a78bfa' : 'var(--border)'}`,
                      transition: 'all .15s',
                    }}>{f}</button>
                  ))}
                </div>
              </Section>

              <Section label="Cor da pele">
                <div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}>
                  {SKIN_COLORS.map((c, i) => (
                    <button key={i} onClick={() => setSkin(i)} style={{
                      width: 36, height: 36, borderRadius: '50%', background: c,
                      border: `3px solid ${skin === i ? '#a78bfa' : 'transparent'}`,
                      boxShadow: skin === i ? '0 0 12px rgba(167,139,250,0.5)' : 'none',
                      transition: 'all .15s',
                    }} />
                  ))}
                </div>
              </Section>
            </>
          )}

          {tab === 1 && (
            <Section label="Estilo de cabelo">
              <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 8 }}>
                {HAIR_STYLES.map((h, i) => (
                  <button key={i} onClick={() => setHair(i)} style={{
                    height: 60, borderRadius: 12,
                    background: h.bg,
                    border: `2px solid ${hair === i ? '#a78bfa' : 'transparent'}`,
                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                    fontSize: 18, transition: 'all .15s',
                    boxShadow: hair === i ? '0 0 16px rgba(167,139,250,0.4)' : 'none',
                  }}>💇</button>
                ))}
              </div>
            </Section>
          )}

          {(tab === 2 || tab === 3 || tab === 4 || tab === 5) && (
            <div style={{ textAlign: 'center', padding: '40px 0', color: 'var(--muted)' }}>
              <Sparkles size={32} style={{ margin: '0 auto 12px', opacity: 0.4 }} />
              <p style={{ fontSize: 14 }}>Em breve mais opções para<br /><strong>{TABS[tab]}</strong></p>
            </div>
          )}
        </div>

        {/* Bottom actions */}
        <div style={{
          padding: '12px 14px', borderTop: '1px solid var(--border)',
          display: 'flex', gap: 8, flexShrink: 0,
        }}>
          <button style={{
            flex: 1, padding: '10px', borderRadius: 12,
            background: 'var(--surface2)', border: '1px solid var(--border)',
            color: 'var(--text-2)', fontSize: 13, fontWeight: 600,
            display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 6,
          }}>
            <Camera size={15} /> Calibrar Face
          </button>
          <button style={{
            flex: 1, padding: '10px', borderRadius: 12,
            background: 'var(--grad)', color: '#fff', fontSize: 13, fontWeight: 700,
            display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 6,
            boxShadow: '0 3px 14px var(--primary-glow)',
          }}>
            <Save size={15} /> Salvar Avatar
          </button>
        </div>
      </div>
    </div>
  )
}

function Section({ label, children }) {
  return (
    <div style={{ marginBottom: 22 }}>
      <div style={{ fontSize: 12, fontWeight: 700, color: 'var(--muted)', marginBottom: 10, letterSpacing: '0.3px' }}>
        {label}
      </div>
      {children}
    </div>
  )
}
