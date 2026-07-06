import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { Sparkles, Mail, Lock, ArrowRight, Eye, EyeOff } from 'lucide-react'
import api from '../api'
import { useAuthStore } from '../store'

export default function Login() {
  const [form, setForm] = useState({ email: '', password: '' })
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)
  const [showPw, setShowPw] = useState(false)
  const { login } = useAuthStore()
  const navigate = useNavigate()

  async function handleSubmit(e) {
    e.preventDefault()
    setError('')
    setLoading(true)
    try {
      const { data } = await api.post('/auth/login', form)
      login(data.data.user, data.data.access_token, data.data.refresh_token)
      navigate('/')
    } catch (err) {
      setError(err.response?.data?.message || err.response?.data?.error || 'Email ou senha incorretos.')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div style={{
      minHeight: '100vh', display: 'flex',
      background: 'var(--bg)',
    }}>
      {/* Left panel — branding */}
      <div style={{
        flex: 1, display: 'none',
        background: 'linear-gradient(145deg, #0e0520 0%, #160b35 50%, #0a0a15 100%)',
        borderRight: '1px solid var(--border)',
        alignItems: 'center', justifyContent: 'center', flexDirection: 'column',
        padding: 48, position: 'relative', overflow: 'hidden',
      }} className="left-panel">
        {/* Glows */}
        <div style={{ position: 'absolute', top: '20%', left: '30%', width: 300, height: 300, borderRadius: '50%', background: 'var(--primary-glow)', filter: 'blur(80px)', opacity: 0.5 }} />
        <div style={{ position: 'absolute', bottom: '20%', right: '20%', width: 200, height: 200, borderRadius: '50%', background: 'var(--accent-glow)', filter: 'blur(60px)', opacity: 0.5 }} />
        <div style={{ position: 'relative', textAlign: 'center' }}>
          <div style={{ fontSize: 80, marginBottom: 16, animation: 'float 4s ease-in-out infinite' }}>🎭</div>
          <h1 style={{ fontSize: 34, fontWeight: 800, letterSpacing: '-1px', marginBottom: 12 }}>
            Seu avatar,<br />sua história
          </h1>
          <p style={{ color: 'var(--text-2)', fontSize: 16, lineHeight: 1.7, maxWidth: 340 }}>
            Crie, compartilhe e conecte-se com o mundo através do seu avatar personalizado.
          </p>
        </div>
      </div>

      {/* Right panel — form */}
      <div style={{
        width: '100%', maxWidth: 480,
        display: 'flex', alignItems: 'center', justifyContent: 'center',
        padding: '32px 40px',
      }}>
        <div style={{ width: '100%', maxWidth: 380 }}>
          {/* Logo */}
          <div style={{ marginBottom: 40 }}>
            <div style={{
              width: 48, height: 48, borderRadius: 14,
              background: 'var(--grad)', marginBottom: 20,
              display: 'flex', alignItems: 'center', justifyContent: 'center',
              boxShadow: '0 0 24px var(--primary-glow)',
            }}>
              <Sparkles size={22} color="#fff" />
            </div>
            <h2 style={{ fontSize: 26, fontWeight: 800, letterSpacing: '-0.5px', marginBottom: 6 }}>
              Bem-vindo de volta
            </h2>
            <p style={{ color: 'var(--muted)', fontSize: 15 }}>
              Entre na sua conta para continuar
            </p>
          </div>

          {/* Error */}
          {error && (
            <div style={{
              background: 'rgba(239,68,68,0.1)', border: '1px solid rgba(239,68,68,0.25)',
              borderRadius: 'var(--radius)', padding: '12px 16px', marginBottom: 24,
              color: '#fca5a5', fontSize: 14, display: 'flex', alignItems: 'center', gap: 8,
            }}>
              ⚠️ {error}
            </div>
          )}

          {/* Form */}
          <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', gap: 16 }}>
            <InputField
              label="Email" type="email" icon={<Mail size={16} />}
              value={form.email} onChange={v => setForm(f => ({ ...f, email: v }))}
              placeholder="seu@email.com"
            />
            <InputField
              label="Senha" type={showPw ? 'text' : 'password'} icon={<Lock size={16} />}
              value={form.password} onChange={v => setForm(f => ({ ...f, password: v }))}
              placeholder="••••••••"
              suffix={
                <button type="button" onClick={() => setShowPw(s => !s)} style={{ background: 'none', color: 'var(--muted)', display: 'flex', padding: 4 }}>
                  {showPw ? <EyeOff size={16} /> : <Eye size={16} />}
                </button>
              }
            />

            <div style={{ textAlign: 'right', marginTop: -8 }}>
              <Link to="#" style={{ fontSize: 13, color: 'var(--muted)', transition: 'color .15s' }}
                onMouseEnter={e => e.target.style.color = 'var(--text)'}
                onMouseLeave={e => e.target.style.color = 'var(--muted)'}
              >Esqueceu a senha?</Link>
            </div>

            <button type="submit" disabled={loading} style={{
              marginTop: 8, width: '100%', padding: '14px',
              borderRadius: 'var(--radius)', fontWeight: 700, fontSize: 15,
              background: loading ? 'var(--surface3)' : 'var(--grad)',
              color: '#fff', letterSpacing: '0.2px',
              boxShadow: loading ? 'none' : '0 4px 20px var(--primary-glow)',
              display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 8,
              transition: 'all .2s',
            }}>
              {loading ? (
                <span style={{ display: 'flex', alignItems: 'center', gap: 8 }}><Spinner /> <span>Entrando...</span></span>
              ) : (
                <span style={{ display: 'flex', alignItems: 'center', gap: 8 }}><span>Entrar</span> <ArrowRight size={16} /></span>
              )}
            </button>
          </form>

          {/* Divider */}
          <div style={{ display: 'flex', alignItems: 'center', gap: 12, margin: '24px 0' }}>
            <div style={{ flex: 1, height: 1, background: 'var(--border)' }} />
            <span style={{ color: 'var(--muted)', fontSize: 13 }}>ou</span>
            <div style={{ flex: 1, height: 1, background: 'var(--border)' }} />
          </div>

          {/* Social login placeholder */}
          <div style={{ display: 'flex', gap: 10 }}>
            {['G', 'A'].map(s => (
              <button key={s} style={{
                flex: 1, padding: '11px', borderRadius: 'var(--radius)',
                background: 'var(--surface2)', border: '1px solid var(--border)',
                color: 'var(--text-2)', fontWeight: 700, fontSize: 15,
                transition: 'all .15s',
              }}
                onMouseEnter={e => e.currentTarget.style.borderColor = 'var(--primary)'}
                onMouseLeave={e => e.currentTarget.style.borderColor = 'var(--border)'}
              >
                {s === 'G' ? '🔵 Google' : '⬛ Apple'}
              </button>
            ))}
          </div>

          <p style={{ textAlign: 'center', marginTop: 28, color: 'var(--muted)', fontSize: 14 }}>
            Não tem conta?{' '}
            <Link to="/register" style={{
              color: 'var(--text)', fontWeight: 600,
              background: 'linear-gradient(135deg,#a78bfa,#f472b6)',
              WebkitBackgroundClip: 'text', WebkitTextFillColor: 'transparent',
            }}>Criar conta grátis</Link>
          </p>
        </div>
      </div>
    </div>
  )
}

export function InputField({ label, type, icon, value, onChange, placeholder, suffix }) {
  const [focused, setFocused] = useState(false)
  return (
    <div>
      <label style={{ display: 'block', fontSize: 13, fontWeight: 600, color: focused ? 'var(--text)' : 'var(--text-2)', marginBottom: 8, transition: 'color .15s' }}>
        {label}
      </label>
      <div style={{
        display: 'flex', alignItems: 'center', gap: 10,
        background: focused ? 'var(--surface2)' : 'var(--surface)',
        border: `1.5px solid ${focused ? 'var(--primary)' : 'var(--border)'}`,
        borderRadius: 'var(--radius)', padding: '0 14px',
        transition: 'all .18s',
        boxShadow: focused ? '0 0 0 3px var(--primary-glow)' : 'none',
      }}>
        <span style={{ color: focused ? 'var(--primary-light, #a78bfa)' : 'var(--muted)', display: 'flex', flexShrink: 0 }}>{icon}</span>
        <input
          type={type} value={value} required placeholder={placeholder}
          onChange={e => onChange(e.target.value)}
          onFocus={() => setFocused(true)}
          onBlur={() => setFocused(false)}
          style={{
            flex: 1, padding: '12px 0',
            background: 'none', border: 'none', color: 'var(--text)',
            outline: 'none', fontSize: 15,
          }}
        />
        {suffix}
      </div>
    </div>
  )
}

function Spinner() {
  return (
    <div style={{
      width: 16, height: 16, border: '2px solid rgba(255,255,255,0.3)',
      borderTopColor: '#fff', borderRadius: '50%',
      animation: 'spin .7s linear infinite',
    }} />
  )
}
