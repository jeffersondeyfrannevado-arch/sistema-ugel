import { useMemo, useState } from 'react'
import { login, registerUser, resendMfaCode, verifyMfaLogin, sendForgotPasswordCode, resetPassword } from '../services/api'
import './Login.css'
import logoGore from '../assets/logo-gore-piura.png'
import logoUgel from '../assets/logo-ugel-piura.png'
import logoSiagie from '../assets/logo-siagie.png'

export default function Login({ onLogin }) {
  const [isLogin, setIsLogin] = useState(true)
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [name, setName] = useState('')
  const [mfaCode, setMfaCode] = useState('')
  const [mfaChallenge, setMfaChallenge] = useState(null)
  const [isForgotPassword, setIsForgotPassword] = useState(false)
  const [forgotPasswordStep, setForgotPasswordStep] = useState(1)
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)

  const isMfaStep = Boolean(mfaChallenge)
  const helperText = useMemo(() => {
    if (isForgotPassword) {
      return forgotPasswordStep === 1
        ? 'Ingresa tu correo electrónico para enviarte un código de recuperación.'
        : 'Ingresa el código de 6 dígitos recibido y tu nueva contraseña.'
    }
    if (isMfaStep) {
      return 'Ingresa el codigo de verificacion enviado al correo para completar el acceso.'
    }

    return isLogin
      ? 'Accede al panel institucional de Matrícula'
      : 'Las cuentas nuevas se registran como usuario estándar'
  }, [isLogin, isMfaStep, isForgotPassword, forgotPasswordStep])

  const persistSession = (data) => {
    localStorage.setItem('auth_token', data.access_token)
    localStorage.setItem('user', JSON.stringify(data.user))
    if (data.expires_at) {
      localStorage.setItem('auth_expires_at', data.expires_at)
    } else {
      localStorage.removeItem('auth_expires_at')
    }
    onLogin(data.user)
  }

  const handleSubmit = async (e) => {
    e.preventDefault()
    setError('')
    setLoading(true)

    try {
      if (isForgotPassword) {
        if (forgotPasswordStep === 1) {
          await sendForgotPasswordCode(email)
          setForgotPasswordStep(2)
          setMfaCode('') // clear code field for entering code in step 2
        } else {
          await resetPassword(email, mfaCode, password)
          alert('Contraseña restablecida exitosamente. Ahora puedes iniciar sesión.')
          setIsForgotPassword(false)
          setForgotPasswordStep(1)
          setMfaCode('')
          setPassword('')
          setIsLogin(true)
        }
      } else if (isMfaStep) {
        const data = await verifyMfaLogin(mfaChallenge.challenge_id, mfaCode)
        persistSession(data)
      } else if (isLogin) {
        const data = await login(email, password)
        if (data.mfa_required) {
          setMfaChallenge(data)
        } else {
          persistSession(data)
        }
      } else {
        const data = await registerUser(name, email, password)
        persistSession(data)
      }
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }

  const handleResendMfa = async () => {
    if (!mfaChallenge) return

    setLoading(true)
    setError('')
    try {
      const data = await resendMfaCode(mfaChallenge.challenge_id)
      setMfaChallenge(data)
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }

  const handleBack = () => {
    setMfaChallenge(null)
    setMfaCode('')
    setIsForgotPassword(false)
    setForgotPasswordStep(1)
    setError('')
  }

  return (
    <div className="login-container">
      <div className="side-logos-container">
        <div className="logo-side logo-left">
          <img src={logoGore} alt="Gobierno Regional de Piura" className="side-logo-img" />
        </div>
        <div className="logo-side logo-right">
          <img src={logoUgel} alt="UGEL Piura" className="side-logo-img" />
        </div>
      </div>

      <div className="login-box">
        <div className="login-siagie-container">
          <img src={logoSiagie} alt="SIAGIE" className="logo-siagie-img" />
        </div>
        <div className="login-header">
          <h2>{isForgotPassword ? 'Recuperar Contraseña' : (isMfaStep ? 'Verificación MFA' : (isLogin ? 'Iniciar Sesión' : 'Crear Cuenta'))}</h2>
          <p>{helperText}</p>
        </div>

        {error && <div className="login-error">{error}</div>}

        <form onSubmit={handleSubmit} className="login-form">
          {isForgotPassword ? (
            <>
              {forgotPasswordStep === 1 ? (
                <div className="form-group">
                  <label>Correo Electrónico</label>
                  <input
                    type="email"
                    value={email}
                    onChange={(e) => setEmail(e.target.value)}
                    required
                    placeholder="correo@ejemplo.com"
                  />
                </div>
              ) : (
                <>
                  <div className="form-group">
                    <label>Código de recuperación</label>
                    <input
                      type="text"
                      inputMode="numeric"
                      value={mfaCode}
                      onChange={(e) => setMfaCode(e.target.value.replace(/\D/g, '').slice(0, 6))}
                      required
                      placeholder="000000"
                    />
                  </div>
                  <div className="form-group">
                    <label>Nueva Contraseña</label>
                    <input
                      type="password"
                      value={password}
                      onChange={(e) => setPassword(e.target.value)}
                      required
                      minLength={8}
                      placeholder="••••••••"
                    />
                  </div>
                </>
              )}
            </>
          ) : (
            <>
              {!isLogin && !isMfaStep && (
                <div className="form-group">
                  <label>Nombre</label>
                  <input
                    type="text"
                    value={name}
                    onChange={(e) => setName(e.target.value)}
                    required={!isLogin}
                    placeholder="Tu nombre completo"
                  />
                </div>
              )}

              {!isMfaStep && (
                <>
                  <div className="form-group">
                    <label>Correo Electrónico</label>
                    <input
                      type="email"
                      value={email}
                      onChange={(e) => setEmail(e.target.value)}
                      required
                      placeholder="correo@ejemplo.com"
                    />
                  </div>

                  <div className="form-group">
                    <label>Contraseña</label>
                    <input
                      type="password"
                      value={password}
                      onChange={(e) => setPassword(e.target.value)}
                      required
                      minLength={8}
                      placeholder="••••••••"
                    />
                  </div>
                </>
              )}

              {isMfaStep && (
                <div className="form-group">
                  <label>Código de verificación</label>
                  <input
                    type="text"
                    inputMode="numeric"
                    value={mfaCode}
                    onChange={(e) => setMfaCode(e.target.value.replace(/\D/g, '').slice(0, 6))}
                    required
                    placeholder="000000"
                  />
                </div>
              )}
            </>
          )}

          <button type="submit" disabled={loading} className="btn-submit">
            {loading
              ? 'Cargando...'
              : isForgotPassword
              ? (forgotPasswordStep === 1 ? 'Enviar código' : 'Restablecer contraseña')
              : isMfaStep
              ? 'Validar código'
              : isLogin
              ? 'Ingresar'
              : 'Registrarse'}
          </button>
        </form>

        <div className="login-footer">
          {isForgotPassword ? (
            <button type="button" className="btn-switch" onClick={handleBack}>
              Volver al inicio de sesión
            </button>
          ) : isMfaStep ? (
            <>
              <button type="button" className="btn-switch" onClick={handleResendMfa}>
                Reenviar código MFA
              </button>
              <button type="button" className="btn-switch" onClick={handleBack}>
                Volver al inicio de sesión
              </button>
            </>
          ) : (
            <>
              {isLogin && (
                <button
                  type="button"
                  className="btn-switch forgot-password-link"
                  style={{ marginBottom: '0.5rem', display: 'block', width: '100%' }}
                  onClick={() => {
                    setIsForgotPassword(true)
                    setForgotPasswordStep(1)
                    setError('')
                  }}
                >
                  ¿Olvidaste tu contraseña?
                </button>
              )}
              <button
                type="button"
                className="btn-switch"
                onClick={() => setIsLogin(!isLogin)}
              >
                {isLogin ? '¿No tienes cuenta? Regístrate' : '¿Ya tienes cuenta? Inicia sesión'}
              </button>
            </>
          )}
        </div>
      </div>
    </div>
  )
}
