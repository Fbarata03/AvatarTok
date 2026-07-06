import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { Sparkles, User, Mail, Lock, ArrowRight, Globe } from 'lucide-react'
import api from '../api'
import { useAuthStore } from '../store'
import { InputField } from './Login'

const STEPS = ['Conta', 'Detalhes', 'Pronto!']

export default function Register() {
  const [step, setStep] = useState(0)
  const [form, setForm] = useState({ username: '', email: '', password: '', birthdate: '', country: 'BR' })
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)
  const { login } = useAuthStore()
  const navigate = useNavigate()

  function set(key) { return v => setForm(f => ({ ...f, [key]: v })) }

  function nextStep(e) {
    e.preventDefault()
    if (step < 1) { setStep(s => s + 1); return }
    handleSubmit()
  }

  async function handleSubmit() {
    setError(''); setLoading(true)
    try {
      const { data } = await api.post('/auth/register', form)
      login(data.data.user, data.data.access_token, data.data.refresh_token)
      setStep(2)
      setTimeout(() => navigate('/'), 1800)
    } catch (err) {
      setError(err.response?.data?.message || err.response?.data?.error || 'Erro ao criar conta.')
      setStep(1)
    } finally {
      setLoading(false)
    }
  }

  return (
    <div style={{
      minHeight: '100vh', display: 'flex', alignItems: 'center', justifyContent: 'center',
      background: 'var(--bg)', padding: '32px 20px',
    }}>
      <div style={{ width: '100%', maxWidth: 420 }}>
        {/* Progress */}
        <div style={{ display: 'flex', gap: 6, marginBottom: 36 }}>
          {STEPS.map((s, i) => (
            <div key={s} style={{ flex: 1, display: 'flex', flexDirection: 'column', alignItems: 'center', gap: 6 }}>
              <div style={{
                height: 3, width: '100%', borderRadius: 3,
                background: i <= step ? 'var(--grad)' : 'var(--surface3)',
                transition: 'background .3s',
              }} />
              <span style={{ fontSize: 11, color: i <= step ? 'var(--text-2)' : 'var(--muted)', fontWeight: i === step ? 600 : 400 }}>
                {s}
              </span>
            </div>
          ))}
        </div>

        {/* Card */}
        <div style={{
          background: 'var(--surface)', border: '1px solid var(--border)',
          borderRadius: 'var(--radius-xl)', padding: 36,
        }}>
          {/* Logo */}
          <div style={{ textAlign: 'center', marginBottom: 32 }}>
            <div style={{
              width: 52, height: 52, borderRadius: 16,
              background: 'var(--grad)', margin: '0 auto 16px',
              display: 'flex', alignItems: 'center', justifyContent: 'center',
              boxShadow: '0 0 28px var(--primary-glow)',
            }}>
              {step === 2 ? '✅' : <Sparkles size={22} color="#fff" />}
            </div>
            <h2 style={{ fontSize: 22, fontWeight: 800, letterSpacing: '-0.4px', marginBottom: 6 }}>
              {step === 0 ? 'Criar sua conta' : step === 1 ? 'Quase lá!' : 'Conta criada! 🎉'}
            </h2>
            <p style={{ color: 'var(--muted)', fontSize: 14 }}>
              {step === 0 ? 'Escolha um nome de usuário' : step === 1 ? 'Mais alguns detalhes' : 'Redirecionando...'}
            </p>
          </div>

          {step < 2 && (
            <>
              {error && (
                <div style={{
                  background: 'rgba(239,68,68,0.1)', border: '1px solid rgba(239,68,68,0.25)',
                  borderRadius: 'var(--radius)', padding: '12px 16px', marginBottom: 20,
                  color: '#fca5a5', fontSize: 14,
                }}>⚠️ {error}</div>
              )}

              <form onSubmit={nextStep} style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
                {step === 0 && (
                  <>
                    <InputField label="Usuário" type="text" icon={<User size={16} />}
                      value={form.username} onChange={set('username')} placeholder="@seuusuario" />
                    <InputField label="Email" type="email" icon={<Mail size={16} />}
                      value={form.email} onChange={set('email')} placeholder="seu@email.com" />
                    <InputField label="Senha" type="password" icon={<Lock size={16} />}
                      value={form.password} onChange={set('password')} placeholder="Mínimo 8 caracteres" />
                  </>
                )}

                {step === 1 && (
                  <>
                    <div>
                      <label style={{ display: 'block', fontSize: 13, fontWeight: 600, color: 'var(--text-2)', marginBottom: 8 }}>
                        Data de nascimento
                      </label>
                      <input type="date" value={form.birthdate} required
                        onChange={e => set('birthdate')(e.target.value)}
                        style={{
                          width: '100%', padding: '12px 14px',
                          background: 'var(--surface)', border: '1.5px solid var(--border)',
                          borderRadius: 'var(--radius)', color: 'var(--text)', outline: 'none',
                          fontSize: 15, colorScheme: 'dark',
                        }} />
                    </div>
                    <div>
                      <label style={{ display: 'block', fontSize: 13, fontWeight: 600, color: 'var(--text-2)', marginBottom: 8 }}>
                        <Globe size={14} style={{ display: 'inline', marginRight: 6 }} />País
                      </label>
                      <select value={form.country} onChange={e => set('country')(e.target.value)}
                        style={{
                          width: '100%', padding: '12px 14px',
                          background: 'var(--surface)', border: '1.5px solid var(--border)',
                          borderRadius: 'var(--radius)', color: 'var(--text)', outline: 'none', fontSize: 15,
                        }}>
                        <option value="BR">🇧🇷 Brasil</option>
                        <option value="US">🇺🇸 Estados Unidos</option>
                        <option value="PT">🇵🇹 Portugal</option>
                        <option value="AO">🇦🇴 Angola</option>
                        <option value="MZ">🇲🇿 Moçambique</option>
                      </select>
                    </div>
                  </>
                )}

                <button type="submit" disabled={loading} style={{
                  marginTop: 8, width: '100%', padding: '14px',
                  borderRadius: 'var(--radius)', fontWeight: 700, fontSize: 15,
                  background: loading ? 'var(--surface3)' : 'var(--grad)',
                  color: '#fff', boxShadow: loading ? 'none' : '0 4px 20px var(--primary-glow)',
                  display: 'flex', alignItems: 'center', justifyContent: 'center', gap: 8,
                }}>
                  {loading ? (
                    <span>Criando...</span>
                  ) : step === 0 ? (
                    <span style={{ display: 'flex', alignItems: 'center', gap: 8 }}><span>Continuar</span> <ArrowRight size={16} /></span>
                  ) : (
                    <span>Criar conta</span>
                  )}
                </button>
              </form>
            </>
          )}

          {step === 2 && (
            <div style={{ textAlign: 'center', padding: '12px 0' }}>
              <div style={{ fontSize: 48, marginBottom: 16 }}>🎭</div>
              <p style={{ color: 'var(--text-2)', fontSize: 15 }}>Bem-vindo ao AvatarTok!</p>
            </div>
          )}
        </div>

        {step < 2 && (
          <p style={{ textAlign: 'center', marginTop: 24, color: 'var(--muted)', fontSize: 14 }}>
            Já tem conta?{' '}
            <Link to="/login" style={{
              fontWeight: 600, color: 'var(--text)',
              background: 'linear-gradient(135deg,#a78bfa,#f472b6)',
              WebkitBackgroundClip: 'text', WebkitTextFillColor: 'transparent',
            }}>Entrar</Link>
          </p>
        )}
      </div>
    </div>
  )
}
