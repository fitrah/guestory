import {
  BookOpenCheck,
  CalendarDays,
  Camera,
  CameraOff,
  CheckCircle2,
  Clock3,
  CreditCard,
  Download,
  LayoutDashboard,
  LogIn,
  Menu,
  QrCode,
  RefreshCw,
  Search,
  Settings,
  Send,
  ShieldCheck,
  UserPlus,
  Trash2,
  Users,
  X,
  XCircle,
} from 'lucide-react'
import type { Html5Qrcode as Html5QrcodeInstance } from 'html5-qrcode'
import { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react'
import './App.css'
import './invitation-builder.css'
import { InvitationBuilderPage, InvitationRenderer, type InvitationConfig } from './InvitationBuilder'

const API_BASE = import.meta.env.VITE_API_BASE_URL ?? '/api'

type InvitePayload = {
  token: string
  event: {
    name: string
    type: string
    date: string
    start_time?: string
    end_time?: string
    venue?: string
    address?: string
    map_url?: string
  }
  guest: {
    name: string
    guest_count: number
    rsvp_status: 'PENDING' | 'ATTENDING' | 'DECLINED'
    attendance_status: string
  }
  qr: {
    status?: string
    token?: string
  }
  qr_svg_url: string
  invitation_config?: InvitationConfig
  slideshow_assets?: { id: number; url: string; order: number; is_cover: boolean }[]
}

type AlbumPhoto = {
  id: number
  guest_name?: string
  file_url?: string
  status?: string
  uploaded_at?: string
}

type AdminPhoto = AlbumPhoto & {
  event_id: number
  guest?: {
    id: number
    guest_code: string
    name: string
    category?: string
  } | null
}

type PhotoSummary = {
  total: number
  pending: number
  approved: number
  rejected: number
}

type ReceiverEvent = {
  id: number
  name: string
  date: string
  start_time?: string
  venue_name?: string
  venue_address?: string
  guest_count: number
  checked_in_count: number
}

type ReceiverGuest = {
  id: number
  guest_code: string
  name: string
  category?: string
  guest_count: number
  attendance_status: string
  check_in?: {
    method: string
    checked_in_time?: string
    actual_guest_count: number
  } | null
}

type ReceiverCheckIn = {
  id: number
  method: string
  actual_guest_count: number
  checked_in_time?: string
  guest: {
    name: string
    category?: string
  }
}

type ValidationResult = {
  code: string
  message: string
  guest?: {
    id: number
    name: string
    category?: string
    guest_count: number
  }
  event?: {
    id: number
    name: string
  }
}

type AdminEventLite = {
  id: number
  name: string
  date?: string
  status?: string
  type?: string
  venue_name?: string
  counts?: {
    guests: number
    check_ins: number
    photos: number
  }
}

type AdminDashboard = {
  metrics: {
    total_guests: number
    confirmed_guests: number
    pending_rsvp: number
    declined: number
    checked_in: number
    not_checked_in: number
    attendance_rate: number
    total_photos: number
  }
  recent_check_ins: {
    guest_name?: string
    receiver_name?: string
    method: string
    actual_guest_count: number
    checked_in_at?: string
  }[]
}

type AdminGuest = AdminGuestLite & {
  category?: string
  email?: string
  guest_count: number
  invitation_status: string
  rsvp_status: string
  attendance_status: string
  invitation?: {
    token?: string
    status?: string
  }
  qr?: {
    token?: string
    status?: string
  }
}

type AttendanceRow = {
  guest_id: number
  guest_code: string
  name: string
  category: string
  rsvp_status: string
  attendance_status: string
  guest_count: number
  actual_guest_count?: number | null
  method?: string | null
  checked_in_time?: string | null
  receiver_name?: string | null
}

type GuestBookRow = {
  check_in_id: number
  guest_name: string
  category: string
  actual_guest_count: number
  method: string
  checked_in_time?: string
  receiver_name?: string
}

type AdminGuestLite = {
  id: number
  guest_code: string
  name: string
  phone?: string
}

type ManagedUser = {
  id: number
  name: string
  email: string
  role: 'SUPERADMIN' | 'EVENT_OWNER'
  status: string
  email_verified_at?: string | null
  events_count?: number
  created_at?: string
}

type EventReceiverAssignment = {
  user_id: number
  name: string
  email: string
  account_role: 'SUPERADMIN' | 'EVENT_OWNER' | 'RECEIVER'
  account_status: string
  assignment_status: 'ACTIVE' | 'REVOKED'
  activation_required: boolean
  activated_at?: string | null
  assigned_at?: string | null
}

type AdminProfile = {
  id: number
  name: string
  email: string
  whatsapp_number?: string | null
  role: 'SUPERADMIN' | 'EVENT_OWNER'
}

const emptyGuestForm = { name: '', phone: '', email: '', category: 'Other', guest_count: '1' }

function AdminSidebar({ active }: { active: string }) {
  const [open, setOpen] = useState(false)
  const groups = [
    { label: 'Workspace', links: [
      { href: '/admin', label: 'Overview & Guests', Icon: LayoutDashboard },
      { href: '/admin/invitation-builder', label: 'Invitation Builder', Icon: CalendarDays },
      { href: '/admin/attendance', label: 'Attendance', Icon: BookOpenCheck },
      { href: '/admin/receivers', label: 'Receivers', Icon: Users },
      { href: '/admin/photos', label: 'Photos', Icon: Camera },
    ] },
    { label: 'Operations', links: [
      { href: '/admin/whatsapp', label: 'WhatsApp', Icon: Send },
      { href: '/admin/billing', label: 'Plan & Billing', Icon: CreditCard },
      { href: '/admin/settings', label: 'Settings', Icon: Settings },
    ] },
  ]

  return <>
    <button className="mobileNavToggle" type="button" aria-label="Buka navigasi admin" aria-expanded={open} onClick={() => setOpen(true)}><Menu size={20} /><span>Menu</span></button>
    {open && <button className="sidebarScrim" type="button" aria-label="Tutup navigasi admin" onClick={() => setOpen(false)} />}
    <aside className={`sidebar ${open ? 'isOpen' : ''}`}>
      <div className="sidebarHeader"><a className="brandMark" href="/admin"><div className="brandGlyph">G</div><div><strong>Guestory</strong><span>Event administration</span></div></a><button className="sidebarClose" type="button" aria-label="Tutup navigasi" onClick={() => setOpen(false)}><X size={19} /></button></div>
      <nav className="navList" aria-label="Guestory admin navigation">
        {groups.map((group) => <div className="navGroup" key={group.label}><span className="navGroupLabel">{group.label}</span>{group.links.map(({ href, label, Icon }) => <a aria-current={active === href ? 'page' : undefined} className={active === href ? 'active' : ''} href={href} key={href}><Icon size={18} /><span>{label}</span></a>)}</div>)}
      </nav>
      <div className="sidebarFooter"><a href="/receiver"><QrCode size={18} /><span>Open Receiver App</span></a><small>Guestory Admin · v1</small></div>
    </aside>
  </>
}

function Modal({ title, description, onClose, children }: { title: string; description: string; onClose: () => void; children: ReactNode }) {
  const dialogRef = useRef<HTMLElement>(null)
  useEffect(() => {
    const previousFocus = document.activeElement as HTMLElement | null
    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') onClose()
      if (event.key !== 'Tab' || !dialogRef.current) return
      const focusable = Array.from(dialogRef.current.querySelectorAll<HTMLElement>('button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), a[href]'))
      if (!focusable.length) return
      const first = focusable[0]
      const last = focusable[focusable.length - 1]
      if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus() }
      else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus() }
    }
    document.addEventListener('keydown', onKeyDown)
    document.body.style.overflow = 'hidden'
    window.requestAnimationFrame(() => dialogRef.current?.querySelector<HTMLElement>('input, select, button')?.focus())
    return () => { document.removeEventListener('keydown', onKeyDown); document.body.style.overflow = ''; previousFocus?.focus() }
  }, [onClose])
  return <div className="modalBackdrop" role="presentation" onMouseDown={(event) => event.target === event.currentTarget && onClose()}><section ref={dialogRef} className="modalCard" role="dialog" aria-modal="true" aria-labelledby="modal-title" aria-describedby="modal-description"><header><div><p className="eyebrow">Guestory Admin</p><h2 id="modal-title">{title}</h2><p id="modal-description">{description}</p></div><button className="iconButton" type="button" aria-label="Tutup" onClick={onClose}><X size={18} /></button></header>{children}</section></div>
}

function normalizeIndonesianPhone(local: string) {
  const digits = local.replace(/\D/g, '').replace(/^0+/, '').replace(/^62/, '')
  return digits ? `62${digits}` : ''
}

type WhatsAppLog = {
  id: number
  recipient: string
  status: string
  message_type: string
  created_at?: string
  guest?: {
    name: string
    guest_code: string
    phone?: string
  } | null
}

const metrics = [
  { label: 'Total Guests', value: '250', tone: 'ink' },
  { label: 'Confirmed', value: '184', tone: 'green' },
  { label: 'Checked In', value: '168', tone: 'blue' },
  { label: 'Photos', value: '342', tone: 'rose' },
]

const guests = [
  ['Budi Santoso', 'Family', 'ATTENDING', 'CHECKED_IN', 'ACTIVE'],
  ['Andi Wijaya', 'Friend', 'PENDING', 'NOT_CHECKED_IN', 'ACTIVE'],
  ['Sinta Dewi', 'VIP', 'ATTENDING', 'CHECKED_IN', 'ACTIVE'],
  ['Maya Putri', 'Colleague', 'DECLINED', 'NOT_CHECKED_IN', 'REVOKED'],
]

const checkIns = [
  ['18:42', 'Budi Santoso', 'QR'],
  ['18:43', 'Sinta Dewi', 'QR'],
  ['18:45', 'Andi Wijaya', 'MANUAL'],
]

function App() {
  const inviteToken = useMemo(() => {
    const match = window.location.pathname.match(/^\/invite\/([^/]+)/)
    return match?.[1]
  }, [])

  if (window.location.pathname.startsWith('/forgot-password')) {
    return <ForgotPasswordPage />
  }

  if (window.location.pathname.startsWith('/reset-password')) {
    return <ResetPasswordPage />
  }

  if (window.location.pathname.startsWith('/register')) {
    return <RegisterPage />
  }

  if (window.location.pathname.startsWith('/verify-email')) {
    return <AccountTokenPage mode="verify" />
  }

  if (window.location.pathname.startsWith('/activate-account')) {
    return <AccountTokenPage mode="activate" />
  }

  if (window.location.pathname.startsWith('/superadmin/users')) {
    return <SuperadminUsersPage />
  }

  if (inviteToken) {
    return <GuestInvitation token={inviteToken} />
  }

  if (window.location.pathname.startsWith('/admin/invitation-builder')) {
    return <InvitationBuilderPage apiBase={API_BASE} Sidebar={AdminSidebar} />
  }

  if (window.location.pathname.startsWith('/admin/attendance')) {
    return <AdminAttendancePage />
  }

  if (window.location.pathname.startsWith('/admin/receivers')) {
    return <AdminReceiversPage />
  }

  if (window.location.pathname.startsWith('/admin/photos')) {
    return <AdminPhotosPage />
  }

  if (window.location.pathname.startsWith('/admin/billing')) {
    return <AdminBillingPage />
  }

  if (window.location.pathname.startsWith('/admin/whatsapp')) {
    return <AdminWhatsAppPage />
  }

  if (window.location.pathname.startsWith('/admin/settings')) {
    return <AdminSettingsPage />
  }

  if (window.location.pathname.startsWith('/prototype')) {
    return <AdminPrototype />
  }

  if (window.location.pathname === '/') {
    return <LandingPage />
  }

  if (window.location.pathname.startsWith('/admin')) {
    return <AdminCmsApp />
  }

  if (window.location.pathname.startsWith('/receiver')) {
    return <ReceiverCheckInApp />
  }

  return <LandingPage />
}

function ForgotPasswordPage() {
  const [email, setEmail] = useState('')
  const [message, setMessage] = useState('Masukkan email admin untuk menerima link reset password.')
  const [isSubmitting, setIsSubmitting] = useState(false)

  async function requestReset() {
    if (!email.trim()) {
      setMessage('Email wajib diisi.')
      return
    }

    setIsSubmitting(true)
    setMessage('Mengirim link reset password...')

    try {
      const response = await fetch(`${API_BASE}/auth/forgot-password`, {
        method: 'POST',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({ email }),
      })
      const json = await response.json().catch(() => ({}))

      setMessage(json.message ?? (response.ok ? 'Jika email terdaftar, link reset password akan dikirim.' : 'Request reset password gagal.'))
    } catch {
      setMessage('Request reset password gagal. Coba lagi sebentar.')
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <main className="adminCmsPage">
      <section className="adminCmsLogin">
        <div className="brandMark">
          <div className="brandGlyph">G</div>
          <div>
            <strong>Guestory</strong>
            <span>Every Guest Has a Story</span>
          </div>
        </div>
        <div>
          <p className="eyebrow">Forgot Password</p>
          <h1>Reset akses admin.</h1>
        </div>
        <label>
          Email
          <input value={email} onChange={(event) => setEmail(event.target.value)} />
        </label>
        <button className="primary wideButton" type="button" onClick={requestReset} disabled={isSubmitting}>
          <Send size={18} />
          Kirim Link Reset
        </button>
        <a className="textLink" href="/admin">Kembali ke login admin</a>
        <p className="statusMessage">{message}</p>
      </section>
    </main>
  )
}

function RegisterPage() {
  const [form, setForm] = useState({ name: '', email: '', password: '', password_confirmation: '', terms_accepted: false, privacy_accepted: false, website: '' })
  const [message, setMessage] = useState('Daftar sebagai event owner. Email harus diverifikasi sebelum login.')
  const [isSubmitting, setIsSubmitting] = useState(false)
  async function register() {
    setIsSubmitting(true)
    try {
      const response = await fetch(`${API_BASE}/auth/register`, { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify(form) })
      const json = await response.json().catch(() => ({})); setMessage(json.message ?? (response.ok ? 'Periksa email untuk link verifikasi.' : 'Pendaftaran gagal.'))
    } catch { setMessage('Pendaftaran gagal. Coba lagi sebentar.') } finally { setIsSubmitting(false) }
  }
  return <main className="adminCmsPage"><section className="adminCmsLogin accountForm">
    <div className="brandMark"><div className="brandGlyph">G</div><div><strong>Guestory</strong><span>Every Guest Has a Story</span></div></div>
    <div><p className="eyebrow">Event Owner</p><h1>Buat akun Guestory.</h1></div>
    <label>Nama<input value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} /></label>
    <label>Email<input type="email" value={form.email} onChange={(event) => setForm({ ...form, email: event.target.value })} /></label>
    <label>Password<input type="password" value={form.password} onChange={(event) => setForm({ ...form, password: event.target.value })} /></label>
    <label>Konfirmasi Password<input type="password" value={form.password_confirmation} onChange={(event) => setForm({ ...form, password_confirmation: event.target.value })} /></label>
    <input className="honeypot" tabIndex={-1} autoComplete="off" value={form.website} onChange={(event) => setForm({ ...form, website: event.target.value })} aria-hidden="true" />
    <label className="checkLabel"><input type="checkbox" checked={form.terms_accepted} onChange={(event) => setForm({ ...form, terms_accepted: event.target.checked })} /> Saya menyetujui syarat layanan.</label>
    <label className="checkLabel"><input type="checkbox" checked={form.privacy_accepted} onChange={(event) => setForm({ ...form, privacy_accepted: event.target.checked })} /> Saya menyetujui kebijakan privasi.</label>
    <button className="primary wideButton" type="button" onClick={register} disabled={isSubmitting}><UserPlus size={18} /> Daftar</button>
    <a className="textLink" href="/admin">Sudah punya akun? Login</a><p className="statusMessage">{message}</p>
  </section></main>
}

function AccountTokenPage({ mode }: { mode: 'verify' | 'activate' }) {
  const token = new URLSearchParams(window.location.search).get('token') ?? ''
  const [password, setPassword] = useState(''); const [confirmation, setConfirmation] = useState('')
  const [message, setMessage] = useState(mode === 'verify' ? 'Verifikasi email untuk mengaktifkan akun.' : 'Buat password untuk mengaktifkan akun.')
  const [done, setDone] = useState(false)
  async function submit() {
    const response = await fetch(`${API_BASE}/auth/${mode === 'verify' ? 'email/verify' : 'activate'}`, { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify(mode === 'verify' ? { token } : { token, password, password_confirmation: confirmation }) })
    const json = await response.json().catch(() => ({})); setMessage(json.message ?? 'Link tidak dapat diproses.'); setDone(response.ok)
  }
  return <main className="adminCmsPage"><section className="adminCmsLogin accountForm">
    <div className="brandMark"><div className="brandGlyph">G</div><div><strong>Guestory</strong><span>Account security</span></div></div>
    <div><p className="eyebrow">{mode === 'verify' ? 'Email Verification' : 'Account Activation'}</p><h1>{mode === 'verify' ? 'Verifikasi email.' : 'Aktifkan akun.'}</h1></div>
    {mode === 'activate' && <><label>Password<input type="password" value={password} onChange={(event) => setPassword(event.target.value)} /></label><label>Konfirmasi Password<input type="password" value={confirmation} onChange={(event) => setConfirmation(event.target.value)} /></label></>}
    {!done && <button className="primary wideButton" type="button" onClick={submit}><ShieldCheck size={18} /> {mode === 'verify' ? 'Verifikasi Email' : 'Aktifkan Akun'}</button>}
    {done && <a className="landingPrimary" href="/admin">Login Guestory</a>}<p className="statusMessage">{message}</p>
  </section></main>
}

function AdminSettingsPage() {
  const [token, setToken] = useState(() => window.localStorage.getItem('guestory_admin_token') ?? '')
  const [profile, setProfile] = useState<AdminProfile | null>(null)
  const [form, setForm] = useState({ name: '', whatsapp: '' })
  const [message, setMessage] = useState('Kelola identitas akun pengirim Anda.')
  const headers = useMemo(() => ({ Accept: 'application/json', 'Content-Type': 'application/json', Authorization: `Bearer ${token}` }), [token])
  useEffect(() => { if (!token) return; fetch(`${API_BASE}/auth/me`, { headers }).then(async (response) => ({ response, json: await response.json() })).then(({ response, json }) => { if (!response.ok) { window.localStorage.removeItem('guestory_admin_token'); setToken(''); return } const user = json.user as AdminProfile; setProfile(user); setForm({ name: user.name, whatsapp: user.whatsapp_number?.replace(/^62/, '') ?? '' }) }).catch(() => setMessage('Profil gagal dimuat.')) }, [headers, token])
  async function save() { const response = await fetch(`${API_BASE}/auth/me`, { method: 'PATCH', headers, body: JSON.stringify({ name: form.name, whatsapp_number: normalizeIndonesianPhone(form.whatsapp) || null }) }); const json = await response.json().catch(() => ({})); if (!response.ok) { setMessage(json.message ?? 'Profil gagal disimpan.'); return } setProfile(json.user); setMessage('Profil berhasil disimpan.') }
  if (!token) return <main className="adminCmsPage"><section className="adminCmsLogin"><h1>Sesi admin diperlukan.</h1><a className="landingPrimary" href="/admin">Login Admin</a></section></main>
  return <main className="adminCmsShell"><AdminSidebar active="/admin/settings" /><section className="workspace"><header className="topbar"><div><p className="eyebrow">Account</p><h1>Profile settings</h1><span>{profile?.email} · {profile?.role}</span></div></header><section className="adminPanel settingsForm"><label>Nama akun<input value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} /></label><label>Nomor WhatsApp pengirim / akun<div className="phoneInput"><span>+62</span><input inputMode="numeric" value={form.whatsapp} onChange={(event) => setForm({ ...form, whatsapp: event.target.value.replace(/\D/g, '') })} placeholder="81234567890" /></div></label><p>Nomor tersimpan: <strong>{profile?.whatsapp_number ? `+${profile.whatsapp_number}` : 'Belum diatur'}</strong></p><button className="primary" type="button" onClick={save}>Simpan Profil</button><p className="statusMessage">{message}</p></section></section></main>
}

function ResetPasswordPage() {
  const params = new URLSearchParams(window.location.search)
  const [email, setEmail] = useState(params.get('email') ?? '')
  const [token] = useState(params.get('token') ?? '')
  const [password, setPassword] = useState('')
  const [passwordConfirmation, setPasswordConfirmation] = useState('')
  const [message, setMessage] = useState('Buat password baru untuk akun Guestory.')
  const [isSubmitting, setIsSubmitting] = useState(false)

  async function resetPassword() {
    if (!email.trim() || !token.trim()) {
      setMessage('Link reset password tidak lengkap.')
      return
    }

    if (password.length < 8) {
      setMessage('Password minimal 8 karakter.')
      return
    }

    setIsSubmitting(true)
    setMessage('Memperbarui password...')

    try {
      const response = await fetch(`${API_BASE}/auth/reset-password`, {
        method: 'POST',
        headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
        body: JSON.stringify({
          email,
          token,
          password,
          password_confirmation: passwordConfirmation,
        }),
      })
      const json = await response.json().catch(() => ({}))

      setMessage(json.message ?? (response.ok ? 'Password berhasil diperbarui.' : 'Reset password gagal.'))
      if (response.ok) {
        window.location.replace('/admin')
        return
      }
    } catch {
      setMessage('Reset password gagal. Coba lagi sebentar.')
    } finally {
      setIsSubmitting(false)
    }
  }

  return (
    <main className="adminCmsPage">
      <section className="adminCmsLogin">
        <div className="brandMark">
          <div className="brandGlyph">G</div>
          <div>
            <strong>Guestory</strong>
            <span>Every Guest Has a Story</span>
          </div>
        </div>
        <div>
          <p className="eyebrow">Reset Password</p>
          <h1>Buat password baru.</h1>
        </div>
        <label>
          Email
          <input value={email} onChange={(event) => setEmail(event.target.value)} />
        </label>
        <label>
          Password Baru
          <input type="password" value={password} onChange={(event) => setPassword(event.target.value)} />
        </label>
        <label>
          Konfirmasi Password
          <input type="password" value={passwordConfirmation} onChange={(event) => setPasswordConfirmation(event.target.value)} />
        </label>
        <button className="primary wideButton" type="button" onClick={resetPassword} disabled={isSubmitting}>
          <ShieldCheck size={18} />
          Simpan Password
        </button>
        <a className="textLink" href="/admin">Login admin</a>
        <p className="statusMessage">{message}</p>
      </section>
    </main>
  )
}

function LandingPage() {
  const capabilities = [
    {
      icon: <QrCode size={22} />,
      title: 'QR identity per tamu',
      copy: 'Satu tamu memiliki satu QR aktif untuk undangan, check-in, guest book, dan aktivitas foto.',
    },
    {
      icon: <BookOpenCheck size={22} />,
      title: 'Guest book otomatis',
      copy: 'Setiap check-in langsung membentuk buku tamu digital dengan waktu, metode, dan petugas penerima.',
    },
    {
      icon: <Camera size={22} />,
      title: 'Album foto event',
      copy: 'Tamu bisa upload foto dari undangan personal, lalu admin mengkurasi album event.',
    },
    {
      icon: <Send size={22} />,
      title: 'Siap WhatsApp',
      copy: 'Pengiriman undangan via WAPI sudah disiapkan dengan delivery log dan mode dry-run aman.',
    },
  ]

  const flow = [
    ['01', 'Admin membuat event dan daftar tamu'],
    ['02', 'Guest membuka undangan personal dan RSVP'],
    ['03', 'Receiver scan QR atau cari tamu manual'],
    ['04', 'Attendance, guest book, dan album terkoneksi'],
  ]

  return (
    <main className="landingPage">
      <nav className="landingNav" aria-label="Guestory">
        <a className="landingBrand" href="/">
          <span>G</span>
          <strong>Guestory</strong>
        </a>
        <div>
          <a href="/invite/invite-demo-budi">Demo Invite</a>
          <a href="/receiver">Receiver</a>
          <a className="landingLogin" href="/admin">Admin</a>
        </div>
      </nav>

      <section className="landingHero">
        <div className="landingHeroCopy">
          <p className="landingEyebrow">Every Guest Has a Story</p>
          <h1>Undangan digital, QR check-in, guest book, dan album foto dalam satu alur tamu.</h1>
          <p>
            Guestory menghubungkan event, tamu, undangan personal, QR identity, kehadiran, dan foto
            event tanpa membuat tamu harus login atau install aplikasi.
          </p>
          <div className="landingActions">
            <a className="landingPrimary" href="/admin">Buka Admin CMS</a>
            <a className="landingSecondary" href="/invite/invite-demo-budi">Lihat Undangan Demo</a>
          </div>
        </div>

        <div className="landingShowcase" aria-label="Guestory live preview">
          <div className="showcaseTop">
            <span>Guestory Live</span>
            <em>Published</em>
          </div>
          <div className="showcaseEvent">
            <span>Andi & Sinta Wedding</span>
            <strong>168</strong>
            <small>checked in guests</small>
          </div>
          <div className="showcaseGrid">
            <div>
              <QrCode size={22} />
              <strong>QR</strong>
              <span>Active</span>
            </div>
            <div>
              <Users size={22} />
              <strong>250</strong>
              <span>Guests</span>
            </div>
            <div>
              <Camera size={22} />
              <strong>342</strong>
              <span>Photos</span>
            </div>
          </div>
          <div className="showcaseScan">
            <CheckCircle2 size={22} />
            <div>
              <strong>CHECK-IN BERHASIL</strong>
              <span>Budi Santoso - 2 people - QR</span>
            </div>
          </div>
        </div>
      </section>

      <section className="landingMetrics" aria-label="Guestory coverage">
        <div>
          <strong>3</strong>
          <span>Role utama: Admin, Receiver, Guest</span>
        </div>
        <div>
          <strong>1 QR</strong>
          <span>Identitas digital untuk satu event dan satu tamu</span>
        </div>
        <div>
          <strong>No login</strong>
          <span>Pengalaman tamu tetap sederhana dan mobile-first</span>
        </div>
      </section>

      <section className="landingSection">
        <div className="landingSectionTitle">
          <p className="landingEyebrow">Core Modules</p>
          <h2>Semua bagian penting event sudah tersambung.</h2>
        </div>
        <div className="landingCards">
          {capabilities.map((item) => (
            <article key={item.title}>
              {item.icon}
              <h3>{item.title}</h3>
              <p>{item.copy}</p>
            </article>
          ))}
        </div>
      </section>

      <section className="landingFlow">
        <div>
          <p className="landingEyebrow">Connected Workflow</p>
          <h2>Dari undangan sampai album, datanya tetap satu jalur.</h2>
        </div>
        <div className="flowList">
          {flow.map(([step, label]) => (
            <div key={step}>
              <strong>{step}</strong>
              <span>{label}</span>
            </div>
          ))}
        </div>
      </section>

      <section className="landingCta">
        <ShieldCheck size={26} />
        <div>
          <h2>Guestory MVP sudah live di SUMO.</h2>
          <p>Frontend, API, QR, receiver, attendance, photo album, dan WAPI dry-run siap untuk demo.</p>
        </div>
        <a className="landingPrimary" href="/admin">Masuk CMS</a>
      </section>
    </main>
  )
}

function SuperadminUsersPage() {
  const [token, setToken] = useState(() => window.localStorage.getItem('guestory_admin_token') ?? '')
  const [email, setEmail] = useState(''); const [password, setPassword] = useState('')
  const [users, setUsers] = useState<ManagedUser[]>([]); const [form, setForm] = useState({ name: '', email: '' })
  const [message, setMessage] = useState('Login SUPERADMIN untuk mengelola event owner.')
  const api = useCallback(async (path: string, options: RequestInit = {}) => { const response = await fetch(`${API_BASE}${path}`, { ...options, headers: { Accept: 'application/json', 'Content-Type': 'application/json', Authorization: `Bearer ${token}`, ...(options.headers ?? {}) } }); const json = await response.json().catch(() => ({})); if (!response.ok) throw new Error(json.message ?? 'Request gagal.'); return json }, [token])
  const loadUsers = useCallback(async () => { try { const json = await api('/superadmin/users'); setUsers(json.users ?? []) } catch (error) { setMessage(error instanceof Error ? error.message : 'Gagal memuat user.') } }, [api])
  async function login() { const response = await fetch(`${API_BASE}/auth/login`, { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json' }, body: JSON.stringify({ email, password }) }); const json = await response.json(); if (!response.ok || json.user?.role !== 'SUPERADMIN') { setMessage('Akun SUPERADMIN tidak valid.'); return }; window.localStorage.setItem('guestory_admin_token', json.access_token); setToken(json.access_token); setMessage('SUPERADMIN terhubung.') }
  async function provision() { try { await api('/superadmin/users', { method: 'POST', body: JSON.stringify(form) }); setForm({ name: '', email: '' }); setMessage('Owner dibuat; link aktivasi dikirim tanpa password plaintext.'); await loadUsers() } catch (error) { setMessage(error instanceof Error ? error.message : 'Provisioning gagal.') } }
  async function updateStatus(user: ManagedUser) { try { await api(`/superadmin/users/${user.id}/status`, { method: 'PATCH', body: JSON.stringify({ status: user.status === 'ACTIVE' ? 'SUSPENDED' : 'ACTIVE' }) }); await loadUsers() } catch (error) { setMessage(error instanceof Error ? error.message : 'Update status gagal.') } }
  async function resend(user: ManagedUser) { try { const json = await api(`/superadmin/users/${user.id}/activation/resend`, { method: 'POST' }); setMessage(json.message) } catch (error) { setMessage(error instanceof Error ? error.message : 'Resend gagal.') } }
  useEffect(() => { if (token) loadUsers() }, [loadUsers, token])
  if (!token) return <main className="adminCmsPage"><section className="adminCmsLogin"><p className="eyebrow">SUPERADMIN</p><h1>Kelola akun owner.</h1><label>Email<input value={email} onChange={(e) => setEmail(e.target.value)} /></label><label>Password<input type="password" value={password} onChange={(e) => setPassword(e.target.value)} /></label><button className="primary" onClick={login}>Login</button><p className="statusMessage">{message}</p></section></main>
  return <main className="adminCmsShell"><aside className="sidebar"><div className="brandMark"><div className="brandGlyph">G</div><div><strong>Guestory</strong><span>Platform administration</span></div></div><nav className="navList"><a href="/admin"><LayoutDashboard size={18} /> Event CMS</a><a className="active" href="/superadmin/users"><Users size={18} /> Users</a></nav></aside><section className="workspace"><header className="topbar"><div><p className="eyebrow">SUPERADMIN</p><h1>User management</h1></div><button onClick={loadUsers}><RefreshCw size={18} /> Refresh</button></header><section className="mainGrid"><article className="adminPanel"><h2>Event owners</h2><div className="guestTable"><div className="tableRow tableHead"><span>Owner</span><span>Role</span><span>Status</span><span>Events</span><span>Action</span></div>{users.map((user) => <div className="tableRow" key={user.id}><span><strong>{user.name}</strong><small>{user.email}</small></span><span>{user.role}</span><span>{user.status}</span><span>{user.events_count ?? 0}</span><span>{user.role === 'EVENT_OWNER' && <>{user.status === 'PENDING_ACTIVATION' && <button onClick={() => resend(user)}>Resend</button>}<button onClick={() => updateStatus(user)}>{user.status === 'ACTIVE' ? 'Suspend' : 'Activate'}</button></>}</span></div>)}</div></article><aside className="adminPanel"><h2>Provision owner</h2><label>Nama<input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} /></label><label>Email<input value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} /></label><button className="primary wideButton" onClick={provision}><UserPlus size={18} /> Kirim aktivasi</button><p className="statusMessage">{message}</p></aside></section></section></main>
}

function AdminReceiversPage() {
  const [token, setToken] = useState(() => window.localStorage.getItem('guestory_admin_token') ?? '')
  const [events, setEvents] = useState<AdminEventLite[]>([])
  const [selectedEventId, setSelectedEventId] = useState<number | null>(null)
  const [receivers, setReceivers] = useState<EventReceiverAssignment[]>([])
  const [form, setForm] = useState({ name: '', email: '', eventIds: [] as number[] })
  const [message, setMessage] = useState('Pilih event untuk mengelola petugas.')
  const headers = useMemo(() => ({ Accept: 'application/json', 'Content-Type': 'application/json', Authorization: `Bearer ${token}` }), [token])

  const loadReceivers = useCallback(async (eventId: number) => {
    const response = await fetch(`${API_BASE}/admin/events/${eventId}/receivers`, { headers })
    const json = await response.json().catch(() => ({}))
    if (!response.ok) throw new Error(json.message ?? 'Gagal memuat petugas.')
    setReceivers(json.receivers ?? [])
  }, [headers])

  const loadEvents = useCallback(async () => {
    const response = await fetch(`${API_BASE}/admin/events`, { headers })
    const json = await response.json().catch(() => ({}))
    if (!response.ok) { setToken(''); setMessage(json.message ?? 'Sesi admin perlu login ulang.'); return }
    setEvents(json.events ?? [])
    setSelectedEventId((current) => current ?? json.events?.[0]?.id ?? null)
  }, [headers])

  async function assignReceiver() {
    if (!selectedEventId || !form.email.trim()) return
    try {
      const response = await fetch(`${API_BASE}/admin/events/${selectedEventId}/receivers`, {
        method: 'POST', headers,
        body: JSON.stringify({ name: form.name || null, email: form.email, event_ids: form.eventIds.length ? form.eventIds : [selectedEventId] }),
      })
      const json = await response.json().catch(() => ({}))
      if (!response.ok) throw new Error(json.message ?? 'Gagal menambahkan petugas.')
      setForm({ name: '', email: '', eventIds: [] })
      setMessage(json.message)
      await loadReceivers(selectedEventId)
    } catch (error) { setMessage(error instanceof Error ? error.message : 'Gagal menambahkan petugas.') }
  }

  async function revoke(receiver: EventReceiverAssignment) {
    if (!selectedEventId) return
    const response = await fetch(`${API_BASE}/admin/events/${selectedEventId}/receivers/${receiver.user_id}`, { method: 'DELETE', headers })
    const json = await response.json().catch(() => ({}))
    setMessage(json.message ?? (response.ok ? 'Akses dicabut.' : 'Gagal mencabut akses.'))
    if (response.ok) await loadReceivers(selectedEventId)
  }

  async function resendActivation(receiver: EventReceiverAssignment) {
    if (!selectedEventId) return
    const response = await fetch(`${API_BASE}/admin/events/${selectedEventId}/receivers/${receiver.user_id}/activation/resend`, { method: 'POST', headers, body: '{}' })
    const json = await response.json().catch(() => ({}))
    setMessage(json.message ?? (response.ok ? 'Link aktivasi dikirim.' : 'Gagal mengirim aktivasi.'))
  }

  useEffect(() => { if (token) loadEvents() }, [loadEvents, token])
  useEffect(() => { if (selectedEventId) loadReceivers(selectedEventId).catch((error) => setMessage(error.message)) }, [loadReceivers, selectedEventId])

  if (!token) return <main className="adminCmsPage"><section className="adminCmsLogin"><h1>Login melalui Admin CMS</h1><a className="landingPrimary" href="/admin">Buka Admin</a><p>{message}</p></section></main>
  return <main className="adminCmsShell"><AdminSidebar active="/admin/receivers" /><section className="workspace"><header className="topbar"><div><p className="eyebrow">Admin → Event → Petugas</p><h1>Petugas event</h1><span>Akun dapat bertugas di banyak event tanpa mengubah role pemilik event.</span></div><select value={selectedEventId ?? ''} onChange={(e) => setSelectedEventId(Number(e.target.value))}>{events.map((event) => <option key={event.id} value={event.id}>{event.name}</option>)}</select></header><section className="mainGrid"><article className="adminPanel"><div className="sectionHeader"><div><p className="eyebrow">Assignments</p><h2>Daftar petugas</h2></div><button onClick={() => selectedEventId && loadReceivers(selectedEventId)}><RefreshCw size={17} /> Refresh</button></div><div className="receiverAssignmentList">{receivers.map((receiver) => <article key={receiver.user_id}><div><strong>{receiver.name}</strong><small>{receiver.email}</small></div><span>{receiver.account_role}</span><span>{receiver.account_status}</span><span>{receiver.assignment_status}</span><div className="inlineActions">{receiver.activation_required && <button onClick={() => resendActivation(receiver)}>Resend aktivasi</button>} {receiver.assignment_status === 'ACTIVE' && <button onClick={() => revoke(receiver)}>Cabut akses</button>}</div></article>)}{receivers.length === 0 && <p>Belum ada petugas untuk event ini.</p>}</div></article><aside className="adminPanel"><p className="eyebrow">Invite / assign</p><h2>Tambah petugas</h2><label>Nama (wajib untuk akun baru)<input value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} /></label><label>Email<input type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} /></label><fieldset className="eventChecklist"><legend>Tugaskan ke event milik Anda</legend>{events.map((event) => <label key={event.id}><input type="checkbox" checked={form.eventIds.includes(event.id)} onChange={(e) => setForm({ ...form, eventIds: e.target.checked ? [...form.eventIds, event.id] : form.eventIds.filter((id) => id !== event.id) })} /> {event.name}</label>)}</fieldset><button className="primary wideButton" onClick={assignReceiver}><UserPlus size={17} /> Tambah petugas</button><p className="statusMessage">{message}</p></aside></section></section></main>
}

function AdminCmsApp() {
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [token, setToken] = useState(() => window.localStorage.getItem('guestory_admin_token') ?? '')
  const [events, setEvents] = useState<AdminEventLite[]>([])
  const [selectedEventId, setSelectedEventId] = useState<number | null>(null)
  const [dashboard, setDashboard] = useState<AdminDashboard | null>(null)
  const [guests, setGuests] = useState<AdminGuest[]>([])
  const [guestSearch, setGuestSearch] = useState('')
  const [guestForm, setGuestForm] = useState(emptyGuestForm)
  const [showGuestModal, setShowGuestModal] = useState(false)
  const [showEventModal, setShowEventModal] = useState(false)
  const [editingGuest, setEditingGuest] = useState<AdminGuest | null>(null)
  const [eventForm, setEventForm] = useState({
    name: '',
    date: new Date().toISOString().slice(0, 10),
    type: 'Wedding',
    venue_name: '',
  })
  const [message, setMessage] = useState('Login admin untuk membuka CMS Guestory.')

  const selectedEvent = events.find((event) => event.id === selectedEventId)

  const adminFetch = useCallback(async function adminFetch<T>(path: string, options: RequestInit = {}) {
    const response = await fetch(`${API_BASE}${path}`, {
      ...options,
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        Authorization: `Bearer ${token}`,
        ...(options.headers ?? {}),
      },
    })
    const json = await response.json().catch(() => ({}))

    if (!response.ok) {
      throw new Error(json.message ?? 'Request admin gagal.')
    }

    return json as T
  }, [token])

  async function loginAdmin() {
    setMessage('Masuk sebagai admin...')
    const response = await fetch(`${API_BASE}/auth/login`, {
      method: 'POST',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, password }),
    })
    const json = await response.json()

    if (!response.ok || !['EVENT_OWNER', 'SUPERADMIN'].includes(json.user?.role)) {
      setMessage(json.message ?? 'Akun admin tidak valid.')
      return
    }

    window.localStorage.setItem('guestory_admin_token', json.access_token)
    setToken(json.access_token)
    setMessage('Admin CMS siap.')
  }

  async function loadEvents(accessToken = token) {
    const response = await fetch(`${API_BASE}/admin/events`, {
      headers: { Accept: 'application/json', Authorization: `Bearer ${accessToken}` },
    })
    const json = await response.json()

    if (!response.ok) {
      window.localStorage.removeItem('guestory_admin_token')
      setToken('')
      setMessage(json.message ?? 'Sesi admin perlu login ulang.')
      return
    }

    setEvents(json.events ?? [])
    setSelectedEventId((current) => current ?? json.events?.[0]?.id ?? null)
  }

  const loadEventWorkspace = useCallback(async function loadEventWorkspace(eventId = selectedEventId) {
    if (!eventId || !token) return

    const query = new URLSearchParams()
    if (guestSearch) query.set('search', guestSearch)

    try {
      const [dashboardJson, guestJson] = await Promise.all([
        adminFetch<AdminDashboard>(`/admin/events/${eventId}/dashboard`),
        adminFetch<{ guests: AdminGuest[] }>(`/admin/events/${eventId}/guests?${query.toString()}`),
      ])

      setDashboard(dashboardJson)
      setGuests(guestJson.guests ?? [])
      setMessage('CMS terhubung ke API.')
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Gagal memuat CMS.')
    }
  }, [adminFetch, guestSearch, selectedEventId, token])

  async function createGuest() {
    if (!selectedEventId) return

    if (!guestForm.name.trim()) {
      setMessage('Nama tamu wajib diisi.')
      return
    }

    try {
      await adminFetch(`/admin/events/${selectedEventId}/guests`, {
        method: 'POST',
        body: JSON.stringify({
          name: guestForm.name,
          phone: normalizeIndonesianPhone(guestForm.phone) || null,
          email: guestForm.email || null,
          category: guestForm.category,
          guest_count: Number(guestForm.guest_count) || 1,
        }),
      })
      setGuestForm(emptyGuestForm)
      setShowGuestModal(false)
      setMessage('Tamu baru berhasil dibuat.')
      await loadEvents()
      await loadEventWorkspace()
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Create guest gagal.')
    }
  }

  async function saveGuest() {
    if (!editingGuest || !selectedEventId) return
    try {
      await adminFetch(`/admin/events/${selectedEventId}/guests/${editingGuest.id}`, { method: 'PATCH', body: JSON.stringify({ name: guestForm.name, phone: normalizeIndonesianPhone(guestForm.phone) || null, email: guestForm.email || null, category: guestForm.category, guest_count: Number(guestForm.guest_count) || 1 }) })
      setEditingGuest(null); setShowGuestModal(false); setGuestForm(emptyGuestForm); setMessage('Data tamu berhasil diperbarui.'); await loadEventWorkspace()
    } catch (error) { setMessage(error instanceof Error ? error.message : 'Update tamu gagal.') }
  }

  async function deleteGuest(guest: AdminGuest) {
    if (!selectedEventId || !window.confirm(`Hapus ${guest.name}? Data invitation, QR, dan attendance terkait juga akan dihapus.`)) return
    try { await adminFetch(`/admin/events/${selectedEventId}/guests/${guest.id}`, { method: 'DELETE' }); setMessage('Tamu berhasil dihapus.'); await loadEvents(); await loadEventWorkspace() }
    catch (error) { setMessage(error instanceof Error ? error.message : 'Hapus tamu gagal.') }
  }

  function openGuestForm(guest?: AdminGuest) {
    setEditingGuest(guest ?? null)
    setGuestForm(guest ? { name: guest.name, phone: guest.phone?.replace(/^62/, '') ?? '', email: guest.email ?? '', category: guest.category ?? 'Other', guest_count: String(guest.guest_count) } : emptyGuestForm)
    setShowGuestModal(true)
  }

  async function createEvent() {
    if (!eventForm.name.trim()) {
      setMessage('Nama event wajib diisi.')
      return
    }

    try {
      const json = await adminFetch<{ event: AdminEventLite }>('/admin/events', {
        method: 'POST',
        body: JSON.stringify({
          name: eventForm.name,
          type: eventForm.type,
          date: eventForm.date,
          venue_name: eventForm.venue_name || null,
          status: 'Draft',
        }),
      })
      setEventForm({ name: '', date: eventForm.date, type: 'Wedding', venue_name: '' })
      await loadEvents()
      setSelectedEventId(json.event.id)
      setShowEventModal(false)
      setMessage('Event draft berhasil dibuat.')
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Create event gagal.')
    }
  }

  async function updateEventStatus(action: 'publish' | 'archive') {
    if (!selectedEventId) return

    try {
      await adminFetch(`/admin/events/${selectedEventId}/${action}`, { method: 'POST' })
      await loadEvents()
      await loadEventWorkspace()
      setMessage(action === 'publish' ? 'Event berhasil dipublish.' : 'Event berhasil diarsipkan.')
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Update status event gagal.')
    }
  }

  async function generateInvitation(guestId: number) {
    if (!selectedEventId) return

    try {
      const json = await adminFetch<{ invitation: { url: string } }>(`/admin/events/${selectedEventId}/guests/${guestId}/invitation/generate`, {
        method: 'POST',
      })
      setMessage(`Invitation siap: ${json.invitation.url}`)
      if (json.invitation.url) window.open(json.invitation.url, '_blank', 'noopener,noreferrer')
      await loadEventWorkspace()
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Generate invitation gagal.')
    }
  }

  async function generateQr(guestId: number, regenerate = false) {
    if (!selectedEventId) return

    try {
      await adminFetch(`/admin/events/${selectedEventId}/guests/${guestId}/qr/${regenerate ? 'regenerate' : 'generate'}`, {
        method: 'POST',
      })
      setMessage(regenerate ? 'QR baru berhasil dibuat.' : 'QR aktif siap.')
      await loadEventWorkspace()
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Generate QR gagal.')
    }
  }

  async function downloadQr(guest: AdminGuest) {
    if (!selectedEventId) return

    const response = await fetch(`${API_BASE}/admin/events/${selectedEventId}/guests/${guest.id}/qr/download`, {
      headers: { Authorization: `Bearer ${token}` },
    })

    if (!response.ok) {
      setMessage('Download QR gagal. Generate QR dulu.')
      return
    }

    const blob = await response.blob()
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `guestory-${guest.guest_code}.svg`
    link.click()
    window.URL.revokeObjectURL(url)
  }

  function logoutAdmin() {
    window.localStorage.removeItem('guestory_admin_token')
    setToken('')
    setDashboard(null)
    setGuests([])
    setMessage('Login admin untuk membuka CMS Guestory.')
  }

  // oxlint-disable-next-line react/set-state-in-effect
  useEffect(() => {
    if (token) {
      // oxlint-disable-next-line react/set-state-in-effect
      loadEvents(token)
    }
  }, [token])

  useEffect(() => {
    if (selectedEventId && token) {
      // oxlint-disable-next-line react/set-state-in-effect
      loadEventWorkspace(selectedEventId)
    }
  }, [loadEventWorkspace, selectedEventId, token])

  if (!token) {
    return (
      <main className="adminCmsPage">
        <section className="adminCmsLogin">
          <div className="brandMark">
            <div className="brandGlyph">G</div>
            <div>
              <strong>Guestory</strong>
              <span>Every Guest Has a Story</span>
            </div>
          </div>
          <div>
            <p className="eyebrow">Admin CMS</p>
            <h1>Kelola event, tamu, invitation, dan QR.</h1>
          </div>
          <label>
            Email
            <input value={email} onChange={(event) => setEmail(event.target.value)} />
          </label>
          <label>
            Password
            <input type="password" value={password} onChange={(event) => setPassword(event.target.value)} />
          </label>
          <button className="primary wideButton" type="button" onClick={loginAdmin}>
            <LogIn size={18} />
            Login Admin
          </button>
          <a className="textLink" href="/forgot-password">Lupa password?</a>
          <a className="textLink" href="/register">Daftar sebagai event owner</a>
          <p className="statusMessage">{message}</p>
        </section>
      </main>
    )
  }

  return (
    <main className="adminCmsShell">
      <AdminSidebar active="/admin" />

      <section className="workspace">
        <header className="topbar">
          <div>
            <p className="eyebrow">Admin CMS</p>
            <h1>{selectedEvent?.name ?? 'Guestory'}</h1>
            <span>{selectedEvent?.status ?? 'Event'} · {selectedEvent?.date ? formatDate(selectedEvent.date) : 'Pilih event'}</span>
          </div>
          <div className="headerActions">
            <select value={selectedEventId ?? ''} onChange={(event) => setSelectedEventId(Number(event.target.value))}>
              {events.map((event) => (
                <option key={event.id} value={event.id}>
                  {event.name}
                </option>
              ))}
            </select>
            <button type="button" onClick={() => loadEventWorkspace()}>
              <RefreshCw size={18} />
              Refresh
            </button>
            <button type="button" onClick={logoutAdmin}>Logout</button>
          </div>
        </header>

        <section className="metricsGrid" aria-label="Event metrics">
          <article className="metric">
            <span>Total Guests</span>
            <strong>{dashboard?.metrics.total_guests ?? 0}</strong>
          </article>
          <article className="metric green">
            <span>Confirmed</span>
            <strong>{dashboard?.metrics.confirmed_guests ?? 0}</strong>
          </article>
          <article className="metric blue">
            <span>Checked In</span>
            <strong>{dashboard?.metrics.checked_in ?? 0}</strong>
          </article>
          <article className="metric rose">
            <span>Photos</span>
            <strong>{dashboard?.metrics.total_photos ?? 0}</strong>
          </article>
        </section>

        <section className="adminCmsGrid">
          <div className="adminPanel">
            <div className="sectionHeader">
              <div>
                <p className="eyebrow">Guest Management</p>
                <h2>Tamu event</h2>
              </div>
              <div className="inlineActions">
                <input value={guestSearch} onChange={(event) => setGuestSearch(event.target.value)} placeholder="Search guest" />
                <button type="button" onClick={() => loadEventWorkspace()}>
                  <Search size={17} />
                  Search
                </button>
              </div>
            </div>

            <button className="primary" type="button" onClick={() => openGuestForm()}><UserPlus size={17} /> Add Guest</button>

            <div className="cmsGuestList">
              {guests.map((guest) => (
                <article key={guest.id}>
                  <div>
                    <strong>{guest.name}</strong>
                    <span>{guest.guest_code} · {guest.category ?? 'Other'} · {guest.guest_count} tamu</span>
                  </div>
                  <em>{guest.rsvp_status}</em>
                  <em>{guest.attendance_status}</em>
                  <em>{guest.invitation_status}</em>
                  <em>{guest.qr?.status ?? 'NO_QR'}</em>
                  <div className="guestActionBar">
                    <button type="button" onClick={() => generateInvitation(guest.id)}>
                      <Send size={15} />
                      Invite
                    </button>
                    <button type="button" onClick={() => generateQr(guest.id, Boolean(guest.qr?.token))}>
                      <QrCode size={15} />
                      {guest.qr?.token ? 'Regen QR' : 'QR'}
                    </button>
                    <button type="button" onClick={() => downloadQr(guest)}>
                      <Download size={15} />
                      SVG
                    </button>
                    <button type="button" onClick={() => openGuestForm(guest)}>Edit</button>
                    <button type="button" onClick={() => deleteGuest(guest)}><Trash2 size={15} /> Delete</button>
                  </div>
                </article>
              ))}
              {guests.length === 0 ? <p>Belum ada tamu untuk filter ini.</p> : null}
            </div>
          </div>

          <aside className="cmsSidePanel">
            <section className="adminPanel">
              <div className="sectionHeader">
                <div>
                  <p className="eyebrow">Event Management</p>
                  <h2>Create & status</h2>
                </div>
                <CalendarDays size={18} />
              </div>
              <button className="primary wideButton" type="button" onClick={() => setShowEventModal(true)}><CalendarDays size={16} /> Create Event</button>
              <div className="eventStatusActions">
                <button type="button" disabled={!selectedEventId || selectedEvent?.status === 'Published'} onClick={() => updateEventStatus('publish')}>Publish</button>
                <button type="button" disabled={!selectedEventId || selectedEvent?.status === 'Archived'} onClick={() => updateEventStatus('archive')}>Archive</button>
              </div>
            </section>

            <section className="adminPanel">
              <div className="sectionHeader">
                <div>
                  <p className="eyebrow">Attendance Rate</p>
                  <h2>{dashboard?.metrics.attendance_rate ?? 0}%</h2>
                </div>
                <ShieldCheck size={20} />
              </div>
              <p>{dashboard?.metrics.not_checked_in ?? 0} pax belum check-in. {dashboard?.metrics.pending_rsvp ?? 0} tamu masih pending RSVP.</p>
            </section>

            <section className="adminPanel">
              <div className="sectionHeader">
                <div>
                  <p className="eyebrow">Recent Check-ins</p>
                  <h2>Gate activity</h2>
                </div>
                <Clock3 size={18} />
              </div>
              <div className="historyList">
                {(dashboard?.recent_check_ins ?? []).map((row) => (
                  <div key={`${row.guest_name}-${row.checked_in_at}`}>
                    <Clock3 size={16} />
                    <span>{row.checked_in_at ?? '-'}</span>
                    <strong>{row.guest_name ?? '-'}</strong>
                    <em>{row.method}</em>
                  </div>
                ))}
              </div>
              {dashboard?.recent_check_ins.length === 0 ? <p>Belum ada check-in.</p> : null}
            </section>

            <section className="adminPanel">
              <div className="quickLinks">
                <a href="/admin/attendance">Attendance & Guest Book</a>
                <a href="/admin/receivers">Petugas event</a>
                <a href="/admin/photos">Photo Moderation</a>
                <a href="/admin/billing">Plan & Billing</a>
                <a href="/admin/whatsapp">WhatsApp Delivery</a>
                <a href="/receiver">Receiver App</a>
              </div>
            </section>
          </aside>
        </section>

        <p className="statusMessage">{message}</p>
      </section>
      {showGuestModal && <Modal title={editingGuest ? 'Edit Guest' : 'Add Guest'} description="Isi identitas tamu dan jumlah orang dalam undangan." onClose={() => setShowGuestModal(false)}><div className="modalForm"><label>Nama lengkap<input autoFocus value={guestForm.name} onChange={(event) => setGuestForm({ ...guestForm, name: event.target.value })} /></label><label>WhatsApp<div className="phoneInput"><span>+62</span><input inputMode="numeric" value={guestForm.phone} onChange={(event) => setGuestForm({ ...guestForm, phone: event.target.value.replace(/\D/g, '') })} placeholder="81234567890" /></div><small>Masukkan nomor lokal tanpa angka 0 di depan.</small></label><label>Email (opsional)<input type="email" value={guestForm.email} onChange={(event) => setGuestForm({ ...guestForm, email: event.target.value })} /></label><label>Kategori<select value={guestForm.category} onChange={(event) => setGuestForm({ ...guestForm, category: event.target.value })}>{['Family','Friend','Colleague','VIP','Other'].map((item) => <option key={item}>{item}</option>)}</select></label><label>Jumlah tamu<input min={1} max={20} type="number" value={guestForm.guest_count} onChange={(event) => setGuestForm({ ...guestForm, guest_count: event.target.value })} /></label><div className="modalActions"><button type="button" onClick={() => setShowGuestModal(false)}>Batal</button><button className="primary" type="button" onClick={editingGuest ? saveGuest : createGuest}>{editingGuest ? 'Simpan Perubahan' : 'Tambah Tamu'}</button></div></div></Modal>}
      {showEventModal && <Modal title="Create Event" description="Buat event draft baru. Publish setelah detail siap." onClose={() => setShowEventModal(false)}><div className="modalForm"><label>Nama event<input autoFocus value={eventForm.name} onChange={(event) => setEventForm({ ...eventForm, name: event.target.value })} /></label><label>Tanggal<input type="date" value={eventForm.date} onChange={(event) => setEventForm({ ...eventForm, date: event.target.value })} /></label><label>Jenis<select value={eventForm.type} onChange={(event) => setEventForm({ ...eventForm, type: event.target.value })}>{['Wedding','Birthday','Engagement','Corporate','Gathering','Other'].map((item) => <option key={item}>{item}</option>)}</select></label><label>Venue (opsional)<input value={eventForm.venue_name} onChange={(event) => setEventForm({ ...eventForm, venue_name: event.target.value })} /></label><div className="modalActions"><button type="button" onClick={() => setShowEventModal(false)}>Batal</button><button className="primary" type="button" onClick={createEvent}>Buat Event Draft</button></div></div></Modal>}
    </main>
  )
}

type BillingPlan = {
  code: 'FREE' | 'BASIC' | 'PREMIUM'
  name: string
  amount: number
  currency: string
  guest_limit: number
  photo_limit: number
  retention_days: number
  zip_download: boolean
  google_photos: boolean
  staff_limit: number
  branding: 'BRANDED' | 'SMALL' | 'NONE'
  popular: boolean
}

type EventBilling = {
  plan_code: string
  status: string
  order_id?: string | null
  amount: number
  paid_at?: string | null
  activated_at?: string | null
  entitlements?: Record<string, string | number | boolean> | null
}

function AdminBillingPage() {
  const [token, setToken] = useState(() => window.localStorage.getItem('guestory_admin_token') ?? '')
  const [events, setEvents] = useState<AdminEventLite[]>([])
  const [selectedEventId, setSelectedEventId] = useState<number | null>(null)
  const [plans, setPlans] = useState<BillingPlan[]>([])
  const [billing, setBilling] = useState<EventBilling | null>(null)
  const [message, setMessage] = useState('Pilih event dan paket untuk mengaktifkan entitlement.')
  const [busy, setBusy] = useState(false)
  const selectedEvent = events.find((event) => event.id === selectedEventId)

  const headers = useMemo(() => ({ Accept: 'application/json', 'Content-Type': 'application/json', Authorization: `Bearer ${token}` }), [token])

  const loadBilling = useCallback(async (eventId: number) => {
    const response = await fetch(`${API_BASE}/admin/events/${eventId}/billing/status`, { headers })
    const json = await response.json().catch(() => ({}))
    if (!response.ok) throw new Error(json.message ?? 'Billing event gagal dimuat.')
    setBilling(json.billing ?? null)
  }, [headers])

  const loadPage = useCallback(async () => {
    if (!token) return
    const [eventResponse, planResponse] = await Promise.all([
      fetch(`${API_BASE}/admin/events`, { headers }),
      fetch(`${API_BASE}/admin/billing/plans`, { headers }),
    ])
    if (!eventResponse.ok || !planResponse.ok) {
      setToken('')
      window.localStorage.removeItem('guestory_admin_token')
      return
    }
    const eventJson = await eventResponse.json()
    const planJson = await planResponse.json()
    setEvents(eventJson.events ?? [])
    setPlans(planJson.plans ?? [])
    setSelectedEventId((current) => current ?? eventJson.events?.[0]?.id ?? null)
  }, [headers, token])

  async function selectPlan(plan: BillingPlan) {
    if (!selectedEventId || busy) return
    setBusy(true)
    setMessage(plan.code === 'FREE' ? 'Mengaktifkan FREE...' : 'Membuat checkout aman...')
    try {
      const path = plan.code === 'FREE' ? 'free' : 'checkout'
      const response = await fetch(`${API_BASE}/admin/events/${selectedEventId}/billing/${path}`, {
        method: 'POST', headers, body: plan.code === 'FREE' ? '{}' : JSON.stringify({ plan_code: plan.code }),
      })
      const json = await response.json().catch(() => ({}))
      if (!response.ok) throw new Error(json.message ?? 'Aktivasi paket gagal.')
      setBilling(json.billing)
      if (json.redirect_url) {
        window.location.assign(json.redirect_url)
        return
      }
      setMessage('Paket FREE aktif dan entitlement siap digunakan.')
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Aktivasi paket gagal.')
    } finally {
      setBusy(false)
    }
  }

  async function syncStatus() {
    if (!selectedEventId || busy) return
    setBusy(true)
    try {
      const response = await fetch(`${API_BASE}/admin/events/${selectedEventId}/billing/sync`, { method: 'POST', headers, body: '{}' })
      const json = await response.json().catch(() => ({}))
      if (!response.ok) throw new Error(json.message ?? 'Sinkronisasi gagal.')
      setBilling(json.billing)
      setMessage(json.billing?.status === 'PAID' ? 'Pembayaran terkonfirmasi. Entitlement aktif.' : `Status pembayaran: ${json.billing?.status ?? 'belum tersedia'}.`)
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Sinkronisasi gagal.')
    } finally {
      setBusy(false)
    }
  }

  useEffect(() => { loadPage() }, [loadPage])
  useEffect(() => {
    if (selectedEventId) loadBilling(selectedEventId).catch((error) => setMessage(error.message))
  }, [loadBilling, selectedEventId])
  useEffect(() => {
    const paymentReturn = new URLSearchParams(window.location.search).get('payment')
    if (paymentReturn) setMessage('Kembali dari halaman pembayaran. Sinkronkan status untuk konfirmasi terbaru.')
  }, [])

  if (!token) {
    return <main className="adminCmsPage"><section className="adminCmsLogin"><h1>Sesi admin diperlukan.</h1><p>Login melalui Admin CMS untuk membuka billing event.</p><a className="landingPrimary" href="/admin">Login Admin</a></section></main>
  }

  return (
    <main className="adminCmsShell">
      <AdminSidebar active="/admin/billing" />
      <section className="workspace">
        <header className="topbar"><div><p className="eyebrow">Per-event Billing</p><h1>{selectedEvent?.name ?? 'Pilih event'}</h1><span>Plan dan pembayaran terpisah dari lifecycle event.</span></div><div className="headerActions"><select value={selectedEventId ?? ''} onChange={(event) => setSelectedEventId(Number(event.target.value))}>{events.map((event) => <option key={event.id} value={event.id}>{event.name}</option>)}</select><button type="button" onClick={syncStatus} disabled={!billing?.order_id || busy}><RefreshCw size={17} /> Sync status</button></div></header>
        <section className="billingStatus adminPanel"><div><p className="eyebrow">Current entitlement</p><h2>{billing ? `${billing.plan_code} · ${billing.status}` : 'Belum ada paket'}</h2></div>{billing?.order_id ? <span>Order {billing.order_id}</span> : null}</section>
        <section className="billingPlans">
          {plans.map((plan) => <article className={`billingPlan ${plan.popular ? 'popular' : ''}`} key={plan.code}>{plan.popular ? <span className="popularBadge">POPULAR</span> : null}<p className="eyebrow">{plan.name}</p><h2>{plan.amount === 0 ? 'Rp0' : `Rp${plan.amount.toLocaleString('id-ID')}`}<small>/event</small></h2><ul><li>{plan.guest_limit.toLocaleString('id-ID')} guests</li><li>{plan.photo_limit.toLocaleString('id-ID')} photos</li><li>Retention {plan.retention_days} hari</li><li>{plan.zip_download ? 'ZIP download' : 'Single-photo download'}</li><li>{plan.google_photos ? 'Google Photos entitlement' : 'Tanpa Google Photos'}</li><li>{plan.staff_limit} staff</li><li>{plan.branding === 'NONE' ? 'Tanpa branding' : plan.branding === 'SMALL' ? 'Small branding' : 'Guestory branded'}</li></ul><button className="primary wideButton" type="button" disabled={!selectedEventId || busy || (billing?.plan_code === plan.code && ['ACTIVE', 'PAID'].includes(billing.status))} onClick={() => selectPlan(plan)}>{billing?.plan_code === plan.code && ['ACTIVE', 'PAID'].includes(billing.status) ? 'Paket aktif' : plan.code === 'FREE' ? 'Aktifkan FREE' : `Pilih ${plan.name}`}</button></article>)}
        </section>
        <p className="statusMessage">{message}</p>
      </section>
    </main>
  )
}

function AdminAttendancePage() {
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [token, setToken] = useState(() => window.localStorage.getItem('guestory_admin_token') ?? '')
  const [events, setEvents] = useState<AdminEventLite[]>([])
  const [selectedEventId, setSelectedEventId] = useState<number | null>(null)
  const [attendance, setAttendance] = useState<AttendanceRow[]>([])
  const [guestBook, setGuestBook] = useState<GuestBookRow[]>([])
  const [statusFilter, setStatusFilter] = useState('')
  const [message, setMessage] = useState('Login admin untuk melihat attendance.')

  const selectedEvent = events.find((event) => event.id === selectedEventId)

  async function loginAdmin() {
    setMessage('Masuk sebagai admin...')
    const response = await fetch(`${API_BASE}/auth/login`, {
      method: 'POST',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, password }),
    })
    const json = await response.json()

    if (!response.ok || !['EVENT_OWNER', 'SUPERADMIN'].includes(json.user?.role)) {
      setMessage(json.message ?? 'Akun admin tidak valid.')
      return
    }

    window.localStorage.setItem('guestory_admin_token', json.access_token)
    setToken(json.access_token)
    setMessage('Admin attendance siap.')
  }

  async function loadEvents(accessToken = token) {
    const response = await fetch(`${API_BASE}/admin/events`, {
      headers: { Accept: 'application/json', Authorization: `Bearer ${accessToken}` },
    })
    const json = await response.json()

    if (!response.ok) {
      setMessage(json.message ?? 'Sesi admin perlu login ulang.')
      setToken('')
      window.localStorage.removeItem('guestory_admin_token')
      return
    }

    setEvents(json.events ?? [])
    setSelectedEventId((current) => current ?? json.events?.[0]?.id ?? null)
  }

  function logoutAdmin() {
    window.localStorage.removeItem('guestory_admin_token')
    setToken('')
    setAttendance([])
    setGuestBook([])
    setMessage('Login admin untuk melihat attendance.')
  }

  async function downloadAttendance() {
    if (!selectedEventId) return

    const response = await fetch(`${API_BASE}/admin/events/${selectedEventId}/attendance/export`, {
      headers: { Authorization: `Bearer ${token}` },
    })

    if (!response.ok) {
      setMessage('Export attendance gagal.')
      return
    }

    const blob = await response.blob()
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `guestory-attendance-${selectedEventId}.csv`
    link.click()
    window.URL.revokeObjectURL(url)
  }

  // oxlint-disable-next-line react/set-state-in-effect
  useEffect(() => {
    if (token) {
      // oxlint-disable-next-line react/set-state-in-effect
      loadEvents(token)
    }
  }, [token])

  // oxlint-disable-next-line react/set-state-in-effect
  useEffect(() => {
    let cancelled = false

    async function loadAttendance() {
      if (!selectedEventId) return

      const query = new URLSearchParams()
      if (statusFilter) query.set('attendance_status', statusFilter)

      const headers = { Accept: 'application/json', Authorization: `Bearer ${token}` }
      const [attendanceResponse, guestBookResponse] = await Promise.all([
        fetch(`${API_BASE}/admin/events/${selectedEventId}/attendance?${query.toString()}`, { headers }),
        fetch(`${API_BASE}/admin/events/${selectedEventId}/guest-book`, { headers }),
      ])
      const attendanceJson = await attendanceResponse.json()
      const guestBookJson = await guestBookResponse.json()

      if (cancelled) return

      if (!attendanceResponse.ok) {
        setMessage(attendanceJson.message ?? 'Attendance gagal dimuat.')
        return
      }

      setAttendance(attendanceJson.attendance ?? [])
      setGuestBook(guestBookJson.guest_book ?? [])
      setMessage('Attendance terupdate dari database.')
    }

    if (token && selectedEventId) {
      loadAttendance()
    }

    return () => {
      cancelled = true
    }
  }, [token, selectedEventId, statusFilter])

  if (!token) {
    return (
      <main className="adminAttendancePage">
        <section className="receiverLogin">
          <div>
            <p className="eyebrow">Guestory Admin</p>
            <h1>Attendance</h1>
            <p>Masuk untuk melihat attendance dan digital guest book.</p>
          </div>
          <label>
            Email
            <input value={email} onChange={(event) => setEmail(event.target.value)} />
          </label>
          <label>
            Password
            <input type="password" value={password} onChange={(event) => setPassword(event.target.value)} />
          </label>
          <button className="primary wideButton" type="button" onClick={loginAdmin}>
            <LogIn size={18} />
            Login Admin
          </button>
          <a className="textLink" href="/forgot-password">Lupa password?</a>
          <p className="statusMessage">{message}</p>
        </section>
      </main>
    )
  }

  return (
    <main className="adminCmsShell"><AdminSidebar active="/admin/attendance" /><section className="workspace">
      <header className="receiverHeader">
        <div>
          <p className="eyebrow">Admin CMS</p>
          <h1>{selectedEvent?.name ?? 'Attendance'}</h1>
          <span>Digital guest book otomatis dari check-in.</span>
        </div>
        <button type="button" onClick={logoutAdmin}>Logout</button>
      </header>

      <section className="attendanceToolbar">
        <label>
          Event
          <select value={selectedEventId ?? ''} onChange={(event) => setSelectedEventId(Number(event.target.value))}>
            {events.map((event) => (
              <option key={event.id} value={event.id}>{event.name}</option>
            ))}
          </select>
        </label>
        <label>
          Attendance
          <select value={statusFilter} onChange={(event) => setStatusFilter(event.target.value)}>
            <option value="">All</option>
            <option value="CHECKED_IN">Checked In</option>
            <option value="NOT_CHECKED_IN">Not Checked In</option>
          </select>
        </label>
        <button className="downloadLink" type="button" onClick={downloadAttendance}>
          <Download size={18} />
          Export CSV
        </button>
      </section>

      <section className="attendanceGrid">
        <div className="attendancePanel">
          <div className="sectionHeader">
            <div>
              <p className="eyebrow">Attendance</p>
              <h2>Guest status</h2>
            </div>
            <span>{attendance.length} rows</span>
          </div>
          <div className="attendanceTable">
            {attendance.map((row) => (
              <div key={row.guest_id}>
                <strong>{row.name}</strong>
                <span>{row.category}</span>
                <span>{row.rsvp_status}</span>
                <em>{row.attendance_status}</em>
                <span>{row.method ?? '-'}</span>
                <span>{row.checked_in_time ?? '-'}</span>
              </div>
            ))}
          </div>
        </div>

        <div className="guestBookPanel">
          <div className="sectionHeader">
            <div>
              <p className="eyebrow">Guest Book</p>
              <h2>Checked-in guests</h2>
            </div>
            <BookOpenCheck size={18} />
          </div>
          {guestBook.map((row) => (
            <div key={row.check_in_id}>
              <Clock3 size={16} />
              <span>{row.checked_in_time ?? '-'}</span>
              <strong>{row.guest_name}</strong>
              <em>{row.method}</em>
            </div>
          ))}
          {guestBook.length === 0 ? <p>Belum ada check-in.</p> : null}
        </div>
      </section>

      <p className="statusMessage">{message}</p>
    </section></main>
  )
}

function AdminWhatsAppPage() {
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [token, setToken] = useState(() => window.localStorage.getItem('guestory_admin_token') ?? '')
  const [events, setEvents] = useState<AdminEventLite[]>([])
  const [guests, setGuests] = useState<AdminGuestLite[]>([])
  const [logs, setLogs] = useState<WhatsAppLog[]>([])
  const [selectedEventId, setSelectedEventId] = useState<number | null>(null)
  const [selectedGuestId, setSelectedGuestId] = useState<number | null>(null)
  const [message, setMessage] = useState('Login admin untuk kirim undangan WhatsApp.')

  const selectedEvent = events.find((event) => event.id === selectedEventId)

  async function loginAdmin() {
    setMessage('Masuk sebagai admin...')
    const response = await fetch(`${API_BASE}/auth/login`, {
      method: 'POST',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, password }),
    })
    const json = await response.json()

    if (!response.ok || !['EVENT_OWNER', 'SUPERADMIN'].includes(json.user?.role)) {
      setMessage(json.message ?? 'Akun admin tidak valid.')
      return
    }

    window.localStorage.setItem('guestory_admin_token', json.access_token)
    setToken(json.access_token)
    setMessage('WhatsApp automation siap.')
  }

  async function loadEvents(accessToken = token) {
    const response = await fetch(`${API_BASE}/admin/events`, {
      headers: { Accept: 'application/json', Authorization: `Bearer ${accessToken}` },
    })
    const json = await response.json()

    if (!response.ok) {
      setToken('')
      window.localStorage.removeItem('guestory_admin_token')
      setMessage(json.message ?? 'Sesi admin perlu login ulang.')
      return
    }

    setEvents(json.events ?? [])
    setSelectedEventId((current) => current ?? json.events?.[0]?.id ?? null)
  }

  async function loadGuestsAndLogs() {
    if (!selectedEventId || !token) return

    const [guestResponse, logResponse] = await Promise.all([
      fetch(`${API_BASE}/admin/events/${selectedEventId}/guests`, {
        headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
      }),
      fetch(`${API_BASE}/admin/events/${selectedEventId}/whatsapp/messages`, {
        headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
      }),
    ])
    const guestJson = await guestResponse.json()
    const logJson = await logResponse.json()

    if (!guestResponse.ok || !logResponse.ok) {
      setMessage(guestJson.message ?? logJson.message ?? 'Gagal memuat WhatsApp data.')
      return
    }

    const nextGuests = guestJson.guests ?? []
    setGuests(nextGuests)
    setSelectedGuestId((current) => current ?? nextGuests?.[0]?.id ?? null)
    setLogs(logJson.messages ?? [])
    setMessage(`${logJson.messages?.length ?? 0} log WhatsApp dimuat.`)
  }

  async function sendSelectedGuest() {
    if (!selectedEventId || !selectedGuestId) return

    const response = await fetch(`${API_BASE}/admin/events/${selectedEventId}/guests/${selectedGuestId}/whatsapp/invitation`, {
      method: 'POST',
      headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
    })
    const json = await response.json()

    if (!response.ok) {
      setMessage(json.message ?? 'Kirim WhatsApp gagal.')
      return
    }

    setMessage(`${json.whatsapp_message?.guest?.name ?? 'Tamu'}: ${json.whatsapp_message?.status}`)
    await loadGuestsAndLogs()
  }

  async function sendBulk() {
    if (!selectedEventId) return

    const response = await fetch(`${API_BASE}/admin/events/${selectedEventId}/whatsapp/invitations/send`, {
      method: 'POST',
      headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
    })
    const json = await response.json()

    if (!response.ok) {
      setMessage(json.message ?? 'Bulk WhatsApp gagal.')
      return
    }

    setMessage(`${json.processed ?? 0} undangan WhatsApp diproses.`)
    await loadGuestsAndLogs()
  }

  function logoutAdmin() {
    window.localStorage.removeItem('guestory_admin_token')
    setToken('')
    setLogs([])
    setGuests([])
    setMessage('Login admin untuk kirim undangan WhatsApp.')
  }

  // oxlint-disable-next-line react/set-state-in-effect
  useEffect(() => {
    if (token) {
      // oxlint-disable-next-line react/set-state-in-effect
      loadEvents(token)
    }
  }, [token])

  // oxlint-disable react-hooks/exhaustive-deps
  // oxlint-disable-next-line react/set-state-in-effect
  useEffect(() => {
    if (selectedEventId && token) {
      // oxlint-disable-next-line react/set-state-in-effect
      loadGuestsAndLogs()
    }
  }, [selectedEventId, token])
  // oxlint-enable react-hooks/exhaustive-deps

  if (!token) {
    return (
      <main className="adminAttendancePage">
        <section className="attendanceLogin">
          <p className="eyebrow">WhatsApp Automation</p>
          <h1>Kirim undangan via WAPI</h1>
          <input value={email} onChange={(event) => setEmail(event.target.value)} />
          <input type="password" value={password} onChange={(event) => setPassword(event.target.value)} />
          <button className="primary" type="button" onClick={loginAdmin}>
            <LogIn size={18} />
            Login Admin
          </button>
          <a className="textLink" href="/forgot-password">Lupa password?</a>
          <p>{message}</p>
        </section>
      </main>
    )
  }

  return (
    <main className="adminCmsShell"><AdminSidebar active="/admin/whatsapp" /><section className="workspace">
      <section className="attendanceHero">
        <div>
          <p className="eyebrow">Guestory WAPI</p>
          <h1>WhatsApp invitation</h1>
          <p>{selectedEvent?.name ?? 'Pilih event untuk kirim undangan.'}</p>
        </div>
        <button type="button" onClick={logoutAdmin}>
          Logout
        </button>
      </section>

      <section className="attendanceControls">
        <label>
          Event
          <select value={selectedEventId ?? ''} onChange={(event) => setSelectedEventId(Number(event.target.value))}>
            {events.map((event) => (
              <option key={event.id} value={event.id}>
                {event.name}
              </option>
            ))}
          </select>
        </label>
        <label>
          Guest
          <select value={selectedGuestId ?? ''} onChange={(event) => setSelectedGuestId(Number(event.target.value))}>
            {guests.map((guest) => (
              <option key={guest.id} value={guest.id}>
                {guest.name} - {guest.phone ?? 'no phone'}
              </option>
            ))}
          </select>
        </label>
        <button className="primary" type="button" onClick={sendSelectedGuest}>
          <Send size={18} />
          Kirim
        </button>
        <button type="button" onClick={sendBulk}>
          <Users size={18} />
          Bulk
        </button>
      </section>

      <section className="attendancePanel">
        <div className="sectionHeader">
          <div>
            <p className="eyebrow">Delivery Log</p>
            <h2>WhatsApp messages</h2>
          </div>
          <RefreshCw size={18} />
        </div>
        <div className="whatsappLogList">
          {logs.map((log) => (
            <div key={log.id}>
              <strong>{log.guest?.name ?? log.recipient}</strong>
              <span>{log.recipient}</span>
              <em>{log.status}</em>
              <small>{log.message_type}</small>
            </div>
          ))}
          {logs.length === 0 ? <p>Belum ada log WhatsApp.</p> : null}
        </div>
      </section>

      <p className="statusMessage">{message}</p>
    </section></main>
  )
}

function AdminPhotosPage() {
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [token, setToken] = useState(() => window.localStorage.getItem('guestory_admin_token') ?? '')
  const [events, setEvents] = useState<AdminEventLite[]>([])
  const [selectedEventId, setSelectedEventId] = useState<number | null>(null)
  const [photos, setPhotos] = useState<AdminPhoto[]>([])
  const [summary, setSummary] = useState<PhotoSummary>({ total: 0, pending: 0, approved: 0, rejected: 0 })
  const [statusFilter, setStatusFilter] = useState('')
  const [message, setMessage] = useState('Login admin untuk mengelola foto.')

  const selectedEvent = events.find((event) => event.id === selectedEventId)

  async function loginAdmin() {
    setMessage('Masuk sebagai admin...')
    const response = await fetch(`${API_BASE}/auth/login`, {
      method: 'POST',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, password }),
    })
    const json = await response.json()

    if (!response.ok || !['EVENT_OWNER', 'SUPERADMIN'].includes(json.user?.role)) {
      setMessage(json.message ?? 'Akun admin tidak valid.')
      return
    }

    window.localStorage.setItem('guestory_admin_token', json.access_token)
    setToken(json.access_token)
    setMessage('Admin photo management siap.')
  }

  async function loadEvents(accessToken = token) {
    const response = await fetch(`${API_BASE}/admin/events`, {
      headers: { Accept: 'application/json', Authorization: `Bearer ${accessToken}` },
    })
    const json = await response.json()

    if (!response.ok) {
      setMessage(json.message ?? 'Sesi admin perlu login ulang.')
      setToken('')
      window.localStorage.removeItem('guestory_admin_token')
      return
    }

    setEvents(json.events ?? [])
    setSelectedEventId((current) => current ?? json.events?.[0]?.id ?? null)
  }

  async function loadPhotos() {
    if (!selectedEventId || !token) return

    const params = new URLSearchParams()
    if (statusFilter) params.set('status', statusFilter)

    const response = await fetch(`${API_BASE}/admin/events/${selectedEventId}/photos?${params.toString()}`, {
      headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
    })
    const json = await response.json()

    if (!response.ok) {
      setMessage(json.message ?? 'Gagal memuat foto.')
      return
    }

    setPhotos(json.photos ?? [])
    setSummary(json.summary ?? { total: 0, pending: 0, approved: 0, rejected: 0 })
    setMessage(`${json.photos?.length ?? 0} foto dimuat.`)
  }

  async function updatePhoto(photoId: number, action: 'approve' | 'reject' | 'delete') {
    if (!selectedEventId || !token) return

    const response = await fetch(`${API_BASE}/admin/events/${selectedEventId}/photos/${photoId}${action === 'delete' ? '' : `/${action}`}`, {
      method: action === 'delete' ? 'DELETE' : 'POST',
      headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
    })
    const json = await response.json()

    if (!response.ok) {
      setMessage(json.message ?? 'Aksi foto gagal.')
      return
    }

    setMessage(json.message ?? 'Foto diperbarui.')
    await loadPhotos()
  }

  function logoutAdmin() {
    window.localStorage.removeItem('guestory_admin_token')
    setToken('')
    setPhotos([])
    setMessage('Login admin untuk mengelola foto.')
  }

  // oxlint-disable-next-line react/set-state-in-effect
  useEffect(() => {
    if (token) {
      // oxlint-disable-next-line react/set-state-in-effect
      loadEvents(token)
    }
  }, [token])

  // oxlint-disable-next-line react/set-state-in-effect, react-hooks/exhaustive-deps
  useEffect(() => {
    if (selectedEventId && token) {
      // oxlint-disable-next-line react/set-state-in-effect
      loadPhotos()
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [selectedEventId, statusFilter, token])

  if (!token) {
    return (
      <main className="adminAttendancePage">
        <section className="attendanceLogin">
          <p className="eyebrow">Admin Photos</p>
          <h1>Kelola album Guestory</h1>
          <input value={email} onChange={(event) => setEmail(event.target.value)} />
          <input type="password" value={password} onChange={(event) => setPassword(event.target.value)} />
          <button className="primary" type="button" onClick={loginAdmin}>
            <LogIn size={18} />
            Login Admin
          </button>
          <a className="textLink" href="/forgot-password">Lupa password?</a>
          <p>{message}</p>
        </section>
      </main>
    )
  }

  return (
    <main className="adminCmsShell"><AdminSidebar active="/admin/photos" /><section className="workspace">
      <section className="attendanceHero">
        <div>
          <p className="eyebrow">Guestory Photos</p>
          <h1>Photo album management</h1>
          <p>{selectedEvent?.name ?? 'Pilih event untuk mengelola foto tamu.'}</p>
        </div>
        <button type="button" onClick={logoutAdmin}>
          Logout
        </button>
      </section>

      <section className="attendanceControls">
        <label>
          Event
          <select value={selectedEventId ?? ''} onChange={(event) => setSelectedEventId(Number(event.target.value))}>
            {events.map((event) => (
              <option key={event.id} value={event.id}>
                {event.name}
              </option>
            ))}
          </select>
        </label>
        <label>
          Status
          <select value={statusFilter} onChange={(event) => setStatusFilter(event.target.value)}>
            <option value="">Semua foto</option>
            <option value="PENDING">Pending</option>
            <option value="APPROVED">Approved</option>
            <option value="REJECTED">Rejected</option>
          </select>
        </label>
        <button type="button" onClick={loadPhotos}>
          <RefreshCw size={18} />
          Refresh
        </button>
      </section>

      <section className="photoSummary">
        <div>
          <span>Total</span>
          <strong>{summary.total}</strong>
        </div>
        <div>
          <span>Pending</span>
          <strong>{summary.pending}</strong>
        </div>
        <div>
          <span>Approved</span>
          <strong>{summary.approved}</strong>
        </div>
        <div>
          <span>Rejected</span>
          <strong>{summary.rejected}</strong>
        </div>
      </section>

      <section className="adminPhotoGrid">
        {photos.map((photo) => (
          <article className="adminPhotoCard" key={photo.id}>
            {photo.file_url ? <img src={absoluteAssetUrl(photo.file_url)} alt={photo.guest_name ?? 'Guestory photo'} /> : null}
            <div>
              <strong>{photo.guest?.name ?? photo.guest_name ?? 'Guestory'}</strong>
              <span>{photo.guest?.guest_code ?? 'No guest code'}</span>
              <em>{photo.status}</em>
            </div>
            <div className="photoActions">
              <button type="button" onClick={() => updatePhoto(photo.id, 'approve')}>
                <CheckCircle2 size={16} />
                Approve
              </button>
              <button type="button" onClick={() => updatePhoto(photo.id, 'reject')}>
                <XCircle size={16} />
                Reject
              </button>
              <button type="button" onClick={() => updatePhoto(photo.id, 'delete')}>
                <Trash2 size={16} />
                Delete
              </button>
            </div>
          </article>
        ))}
        {photos.length === 0 ? <p>Belum ada foto untuk filter ini.</p> : null}
      </section>

      <p className="statusMessage">{message}</p>
    </section></main>
  )
}

function ReceiverCheckInApp() {
  const [email, setEmail] = useState('receiver@guestory.local')
  const [password, setPassword] = useState('')
  const [token, setToken] = useState(() => window.localStorage.getItem('guestory_receiver_token') ?? '')
  const [events, setEvents] = useState<ReceiverEvent[]>([])
  const [selectedEventId, setSelectedEventId] = useState<number | null>(null)
  const [scanToken, setScanToken] = useState('demo-qr-budi')
  const [validation, setValidation] = useState<ValidationResult | null>(null)
  const [actualGuestCount, setActualGuestCount] = useState(1)
  const [history, setHistory] = useState<ReceiverCheckIn[]>([])
  const [searchTerm, setSearchTerm] = useState('')
  const [searchResults, setSearchResults] = useState<ReceiverGuest[]>([])
  const [message, setMessage] = useState('Login receiver untuk mulai check-in.')
  const [scannerActive, setScannerActive] = useState(false)

  const selectedEvent = events.find((event) => event.id === selectedEventId)

  useEffect(() => {
    if (token) {
      loadReceiverEvents(token)
    }
  }, [token])

  useEffect(() => {
    if (token && selectedEventId) {
      loadHistory(token, selectedEventId)
    }
  }, [token, selectedEventId])

  async function requestApi<T>(path: string, options: RequestInit = {}) {
    const response = await fetch(`${API_BASE}${path}`, {
      ...options,
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        Authorization: `Bearer ${token}`,
        ...(options.headers ?? {}),
      },
    })
    const json = await response.json().catch(() => ({}))

    if (!response.ok) {
      throw new Error(json.message ?? 'Koneksi bermasalah. Silakan coba lagi.')
    }

    return json as T
  }

  async function loginReceiver() {
    setMessage('Masuk sebagai receiver...')
    const response = await fetch(`${API_BASE}/auth/login`, {
      method: 'POST',
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, password }),
    })
    const json = await response.json()

    if (!response.ok || json.user?.role !== 'RECEIVER') {
      setMessage(json.message ?? 'Akun receiver tidak valid.')
      return
    }

    window.localStorage.setItem('guestory_receiver_token', json.access_token)
    setToken(json.access_token)
    setMessage('Receiver siap melakukan check-in.')
  }

  async function loadReceiverEvents(accessToken = token) {
    const response = await fetch(`${API_BASE}/receiver/events`, {
      headers: { Accept: 'application/json', Authorization: `Bearer ${accessToken}` },
    })
    const json = await response.json()

    if (!response.ok) {
      setMessage(json.message ?? 'Sesi receiver perlu login ulang.')
      setToken('')
      window.localStorage.removeItem('guestory_receiver_token')
      return
    }

    setEvents(json.events ?? [])
    setSelectedEventId((current) => current ?? json.events?.[0]?.id ?? null)
  }

  async function validateToken(rawValue = scanToken) {
    if (!selectedEventId) return

    const parsedToken = extractQrToken(rawValue)
    setScanToken(parsedToken)
    setMessage('Memvalidasi QR...')

    try {
      const json = await requestApi<ValidationResult>(`/receiver/events/${selectedEventId}/check-in/validate`, {
        method: 'POST',
        body: JSON.stringify({ token: parsedToken }),
      })
      setValidation(json)
      setActualGuestCount(json.guest?.guest_count ?? 1)
      setMessage(json.message)
    } catch (error) {
      setValidation(null)
      setMessage(error instanceof Error ? error.message : 'QR Code tidak valid.')
    }
  }

  async function confirmQrCheckIn() {
    if (!selectedEventId || !validation?.guest) return

    setMessage('Menyimpan check-in...')
    try {
      const json = await requestApi<ValidationResult>('/receiver/check-in/confirm', {
        method: 'POST',
        body: JSON.stringify({ token: scanToken, method: 'QR', actual_guest_count: actualGuestCount }),
      })
      setMessage(json.message)
      setValidation(null)
      await loadHistory()
      await loadReceiverEvents()
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Check-in gagal.')
    }
  }

  async function searchGuest() {
    if (!selectedEventId) return

    const query = new URLSearchParams({ q: searchTerm, attendance_status: 'NOT_CHECKED_IN' })
    setMessage('Mencari tamu...')
    const json = await requestApi<{ guests: ReceiverGuest[] }>(`/receiver/events/${selectedEventId}/guests/search?${query.toString()}`)
    setSearchResults(json.guests)
    setMessage(json.guests.length ? 'Pilih tamu untuk manual check-in.' : 'Tamu tidak ditemukan.')
  }

  async function manualCheckIn(guest: ReceiverGuest) {
    if (!selectedEventId) return

    setMessage(`Manual check-in ${guest.name}...`)
    try {
      const json = await requestApi<ValidationResult>(`/receiver/events/${selectedEventId}/check-ins/manual`, {
        method: 'POST',
        body: JSON.stringify({ guest_id: guest.id, actual_guest_count: guest.guest_count }),
      })
      setMessage(json.message)
      setSearchResults([])
      setSearchTerm('')
      await loadHistory()
      await loadReceiverEvents()
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Manual check-in gagal.')
    }
  }

  async function loadHistory(accessToken = token, eventId = selectedEventId) {
    if (!eventId) return

    const response = await fetch(`${API_BASE}/receiver/events/${eventId}/check-ins/recent?limit=10`, {
      headers: { Accept: 'application/json', Authorization: `Bearer ${accessToken}` },
    })
    const json = await response.json()

    if (response.ok) {
      setHistory(json.check_ins ?? [])
    }
  }

  function logoutReceiver() {
    window.localStorage.removeItem('guestory_receiver_token')
    setToken('')
    setEvents([])
    setHistory([])
    setValidation(null)
    setMessage('Login receiver untuk mulai check-in.')
  }

  if (!token) {
    return (
      <main className="receiverApp">
        <section className="receiverLogin">
          <div>
            <p className="eyebrow">Guestory Receiver</p>
            <h1>Check-in event</h1>
            <p>Masuk sebagai petugas untuk scan QR dan manual check-in tamu.</p>
          </div>
          <label>
            Email
            <input value={email} onChange={(event) => setEmail(event.target.value)} />
          </label>
          <label>
            Password
            <input type="password" value={password} onChange={(event) => setPassword(event.target.value)} />
          </label>
          <button className="primary wideButton" type="button" onClick={loginReceiver}>
            <LogIn size={18} />
            Login Receiver
          </button>
          <a className="textLink" href="/forgot-password">Lupa password?</a>
          <p className="statusMessage">{message}</p>
        </section>
      </main>
    )
  }

  return (
    <main className="receiverApp">
      <header className="receiverHeader">
        <div>
          <p className="eyebrow">Receiver</p>
          <h1>{selectedEvent?.name ?? 'Pilih Event'}</h1>
          <span>{selectedEvent?.venue_name ?? 'Venue event'}</span>
        </div>
        <button type="button" onClick={logoutReceiver}>
          Logout
        </button>
      </header>

      <section className="receiverStatus">
        <label>
          Event
          <select
            value={selectedEventId ?? ''}
            onChange={(event) => {
              setSelectedEventId(Number(event.target.value))
              setValidation(null)
            }}
          >
            {events.map((event) => (
              <option key={event.id} value={event.id}>
                {event.name}
              </option>
            ))}
          </select>
        </label>
        <div>
          <strong>{selectedEvent?.checked_in_count ?? 0}</strong>
          <span>checked-in dari {selectedEvent?.guest_count ?? 0} tamu</span>
        </div>
      </section>

      <section className="receiverScanner">
        <div className="sectionHeader">
          <div>
            <p className="eyebrow">Scanner</p>
            <h2>Scan QR tamu</h2>
          </div>
          <button type="button" onClick={() => setScannerActive((active) => !active)}>
            {scannerActive ? <CameraOff size={18} /> : <Camera size={18} />}
            {scannerActive ? 'Stop' : 'Camera'}
          </button>
        </div>

        {scannerActive ? <QrCameraScanner onScan={validateToken} /> : null}

        <div className="manualToken">
          <input value={scanToken} onChange={(event) => setScanToken(event.target.value)} placeholder="Paste token atau URL QR" />
          <button className="primary" type="button" onClick={() => validateToken()}>
            <QrCode size={18} />
            Validate
          </button>
        </div>

        <div className={`receiverResult ${validation?.code === 'CHECK_IN_ALLOWED' ? 'success' : ''}`}>
          <strong>{validation?.guest?.name ?? message}</strong>
          <span>{validation?.guest ? `${validation.guest.guest_count} tamu - ${validation.guest.category ?? 'Guest'}` : 'Siap scan QR berikutnya.'}</span>
          {validation?.code === 'CHECK_IN_ALLOWED' ? (
            <div className="countStepper">
              <label>
                Hadir aktual
                <input
                  min={1}
                  max={20}
                  type="number"
                  value={actualGuestCount}
                  onChange={(event) => setActualGuestCount(Number(event.target.value))}
                />
              </label>
              <button className="primary" type="button" onClick={confirmQrCheckIn}>
                <CheckCircle2 size={18} />
                Check-in
              </button>
            </div>
          ) : null}
        </div>
      </section>

      <section className="receiverSearch">
        <div className="sectionHeader">
          <div>
            <p className="eyebrow">Manual</p>
            <h2>Search guest</h2>
          </div>
          <RefreshCw size={18} />
        </div>
        <div className="manualToken">
          <input value={searchTerm} onChange={(event) => setSearchTerm(event.target.value)} placeholder="Nama, kode, atau nomor HP" />
          <button type="button" onClick={searchGuest}>
            <Search size={18} />
            Search
          </button>
        </div>
        <div className="receiverGuestList">
          {searchResults.map((guest) => (
            <button key={guest.id} type="button" onClick={() => manualCheckIn(guest)}>
              <strong>{guest.name}</strong>
              <span>{guest.guest_code} - {guest.guest_count} tamu</span>
            </button>
          ))}
        </div>
      </section>

      <section className="receiverHistory">
        <div className="sectionHeader">
          <div>
            <p className="eyebrow">Recent</p>
            <h2>Check-ins</h2>
          </div>
          <Clock3 size={18} />
        </div>
        {history.map((item) => (
          <div key={item.id}>
            <span>{item.checked_in_time ?? '-'}</span>
            <strong>{item.guest.name}</strong>
            <em>{item.method}</em>
          </div>
        ))}
      </section>
    </main>
  )
}

function QrCameraScanner({ onScan }: { onScan: (token: string) => void }) {
  useEffect(() => {
    let scanner: Html5QrcodeInstance | null = null
    let running = true

    import('html5-qrcode')
      .then(({ Html5Qrcode }) => {
        if (!running) return

        scanner = new Html5Qrcode('guestory-qr-reader')

        const activeScanner = scanner

        return activeScanner.start(
          { facingMode: 'environment' },
          { fps: 10, qrbox: { width: 240, height: 240 } },
          (decodedText: string) => {
            if (!running || !scanner) return
            running = false
            onScan(decodedText)
            scanner.stop().catch(() => undefined)
          },
          () => undefined,
        )
      })
      .catch(() => {
        running = false
      })

    return () => {
      running = false
      scanner?.stop().catch(() => undefined)
    }
  }, [onScan])

  return <div className="qrReader" id="guestory-qr-reader" />
}

function GuestInvitation({ token }: { token: string }) {
  const [invite, setInvite] = useState<InvitePayload | null>(null)
  const [photos, setPhotos] = useState<AlbumPhoto[]>([])
  const [selectedPhoto, setSelectedPhoto] = useState<File | null>(null)
  const [status, setStatus] = useState<'loading' | 'ready' | 'error'>('loading')
  const [message, setMessage] = useState('Memuat undangan...')

  useEffect(() => {
    let cancelled = false

    async function loadInvite() {
      try {
        const [inviteResponse, albumResponse] = await Promise.all([
          fetch(`${API_BASE}/invite/${token}`),
          fetch(`${API_BASE}/invite/${token}/album`),
        ])

        if (!inviteResponse.ok) {
          throw new Error('Undangan tidak ditemukan atau belum tersedia.')
        }

        const inviteJson = (await inviteResponse.json()) as InvitePayload
        const albumJson = albumResponse.ok ? await albumResponse.json() : { photos: [] }

        if (!cancelled) {
          setInvite(inviteJson)
          setPhotos(albumJson.photos ?? [])
          setStatus('ready')
          setMessage('')
        }
      } catch (error) {
        if (!cancelled) {
          setStatus('error')
          setMessage(error instanceof Error ? error.message : 'Koneksi bermasalah. Silakan coba lagi.')
        }
      }
    }

    loadInvite()

    return () => {
      cancelled = true
    }
  }, [token])

  async function updateRsvp(rsvpStatus: 'ATTENDING' | 'DECLINED') {
    if (!invite) return

    setMessage('Menyimpan RSVP...')
    const response = await fetch(`${API_BASE}/invite/${token}/rsvp`, {
      method: 'PATCH',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ rsvp_status: rsvpStatus }),
    })

    if (!response.ok) {
      setMessage('RSVP gagal disimpan. Silakan coba lagi.')
      return
    }

    const json = await response.json()
    setInvite({
      ...invite,
      guest: {
        ...invite.guest,
        rsvp_status: json.guest.rsvp_status,
      },
    })
    setMessage('RSVP berhasil disimpan.')
  }

  async function uploadPhoto() {
    if (!selectedPhoto) {
      setMessage('Pilih foto dulu sebelum upload.')
      return
    }

    const formData = new FormData()
    formData.append('photo', selectedPhoto)
    setMessage('Mengupload foto...')

    const response = await fetch(`${API_BASE}/invite/${token}/photos`, {
      method: 'POST',
      headers: { Accept: 'application/json' },
      body: formData,
    })
    const json = await response.json()

    if (!response.ok) {
      setMessage(json.message ?? 'Foto gagal diupload. Silakan coba lagi.')
      return
    }

    setSelectedPhoto(null)
    setMessage('Foto berhasil diupload dan menunggu persetujuan admin.')
  }

  if (status === 'loading' || !invite) {
    return (
      <main className="invitePage centerState">
        <strong>Guestory</strong>
        <p>{message}</p>
      </main>
    )
  }

  if (status === 'error') {
    return (
      <main className="invitePage centerState">
        <strong>Guestory</strong>
        <p>{message}</p>
      </main>
    )
  }

  return <main className="publicInvitationPage"><InvitationRenderer invite={invite} qrUrl={`${API_BASE}/invite/${token}/qr.svg`} photos={photos} slideshowAssets={invite.slideshow_assets ?? []} message={message} selectedPhotoName={selectedPhoto?.name} onRsvp={updateRsvp} onPhotoSelect={setSelectedPhoto} onPhotoUpload={uploadPhoto} assetUrl={absoluteAssetUrl} /></main>
}

function AdminPrototype() {
  return (
    <main className="shell">
      <aside className="sidebar">
        <div className="brandMark">
          <div className="brandGlyph">G</div>
          <div>
            <strong>Guestory</strong>
            <span>Every Guest Has a Story</span>
          </div>
        </div>

        <nav className="navList" aria-label="Guestory navigation">
          <a className="active" href="#dashboard">
            <LayoutDashboard size={18} /> Dashboard
          </a>
          <a href="#guests">
            <Users size={18} /> Guests
          </a>
          <a href="#qr">
            <QrCode size={18} /> QR Codes
          </a>
          <a href="#book">
            <BookOpenCheck size={18} /> Guest Book
          </a>
          <a href="#photos">
            <Camera size={18} /> Photos
          </a>
        </nav>
      </aside>

      <section className="workspace">
        <header className="topbar">
          <div>
            <p className="eyebrow">Guestory MVP</p>
            <h1>Andi & Sinta Wedding</h1>
          </div>
          <div className="headerActions">
            <button type="button" aria-label="Search guests">
              <Search size={18} />
              <span>Search</span>
            </button>
            <button type="button" className="primary">
              <Send size={18} />
              <span>Send Invite</span>
            </button>
          </div>
        </header>

        <section className="metricsGrid" id="dashboard" aria-label="Event metrics">
          {metrics.map((metric) => (
            <article className={`metric ${metric.tone}`} key={metric.label}>
              <span>{metric.label}</span>
              <strong>{metric.value}</strong>
            </article>
          ))}
        </section>

        <section className="mainGrid">
          <div className="adminPanel" id="guests">
            <div className="sectionHeader">
              <div>
                <p className="eyebrow">Admin CMS</p>
                <h2>Guest Management</h2>
              </div>
              <button type="button">
                <Download size={17} />
                <span>Export</span>
              </button>
            </div>

            <div className="guestTable" role="table" aria-label="Guest list">
              <div className="tableRow tableHead" role="row">
                <span>Name</span>
                <span>Category</span>
                <span>RSVP</span>
                <span>Attendance</span>
                <span>QR</span>
              </div>
              {guests.map((guest) => (
                <div className="tableRow" role="row" key={guest[0]}>
                  {guest.map((item) => (
                    <span key={item}>{item}</span>
                  ))}
                </div>
              ))}
            </div>
          </div>

          <div className="phonePreview">
            <div className="phoneScreen">
              <p className="eyebrow">Dear Budi Santoso</p>
              <h2>Andi & Sinta</h2>
              <div className="datePill">
                <CalendarDays size={16} /> 24 Oktober 2026
              </div>
              <div className="qrBox" id="qr" aria-label="QR preview">
                <div className="qrPattern" />
              </div>
              <p className="inviteCopy">Tunjukkan QR pribadi ini saat tiba di venue.</p>
              <button type="button" className="wideButton">
                <CheckCircle2 size={18} />
                RSVP Attending
              </button>
            </div>
          </div>

          <div className="receiverPanel">
            <div className="sectionHeader">
              <div>
                <p className="eyebrow">Receiver</p>
                <h2>Check-in Gate A</h2>
              </div>
              <span className="liveBadge">Ready</span>
            </div>

            <div className="scanBox">
              <QrCode size={44} />
              <strong>SCAN QR</strong>
              <span>Validation target under 2 seconds</span>
            </div>

            <div className="resultBox">
              <ShieldCheck size={22} />
              <div>
                <strong>CHECK-IN BERHASIL</strong>
                <span>Budi Santoso - 2 people - 18:42</span>
              </div>
            </div>

            <div className="historyList" id="book">
              {checkIns.map((row) => (
                <div key={row.join('-')}>
                  <Clock3 size={16} />
                  <span>{row[0]}</span>
                  <strong>{row[1]}</strong>
                  <em>{row[2]}</em>
                </div>
              ))}
            </div>
          </div>

          <div className="photoPanel" id="photos">
            <div className="sectionHeader">
              <div>
                <p className="eyebrow">Album</p>
                <h2>Photo Activity</h2>
              </div>
              <Camera size={20} />
            </div>
            <div className="photoGrid">
              <div />
              <div />
              <div />
              <div />
            </div>
            <p>342 approved photos tied to this event. Budi Santoso uploaded 3 photos.</p>
          </div>
        </section>
      </section>
    </main>
  )
}

function formatDate(value: string) {
  return new Intl.DateTimeFormat('id-ID', {
    dateStyle: 'full',
  }).format(new Date(value))
}

function extractQrToken(value: string) {
  const trimmed = value.trim()

  try {
    const url = new URL(trimmed)
    const match = url.pathname.match(/\/(?:api\/)?g\/([^/]+)/)

    if (match?.[1]) {
      return decodeURIComponent(match[1])
    }
  } catch {
    // Plain token input is expected for manual fallback and local testing.
  }

  return trimmed
}

function absoluteAssetUrl(value: string) {
  if (value.startsWith('http://') || value.startsWith('https://')) {
    return value
  }

  const apiRoot = API_BASE.replace(/\/api\/?$/, '')

  return `${apiRoot}${value.startsWith('/') ? value : `/${value}`}`
}

export default App
