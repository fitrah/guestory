import { BookOpenCheck, ExternalLink, Search, ShieldCheck } from 'lucide-react'
import { useEffect, useMemo, useState, type ComponentType } from 'react'

export type GuideRole = 'SUPERADMIN' | 'EVENT_OWNER' | 'RECEIVER'
type GuideSection = { id: string; title: string; summary: string; roles: GuideRole[]; steps: string[]; tips?: string[] }
type Profile = { role?: string; name?: string }

const FULL_GUIDE_URL = 'https://github.com/fitrah/guestory/blob/main/docs/user-guide.md'

// oxlint-disable-next-line react/only-export-components -- exported for source-owned guide-content regression tests.
export const sections: GuideSection[] = [
  { id: 'superadmin-users', title: 'Kelola Event Owner', summary: 'Provisioning, aktivasi, dan status akun owner.', roles: ['SUPERADMIN'], steps: ['Buka Users dari area SUPERADMIN.', 'Isi nama dan email di Provision owner, lalu pilih Kirim aktivasi.', 'Untuk PENDING_ACTIVATION gunakan Resend; jangan gunakan Activate sebagai pengganti pembuatan password.', 'Suspend mencabut sesi owner aktif; Activate mengaktifkan kembali akun yang sebelumnya ditangguhkan.'], tips: ['SUPERADMIN tetap hanya melihat event miliknya sendiri di Event CMS.'] },
  { id: 'event-context', title: 'Pilih event yang benar', summary: 'Current event berlaku lintas halaman dan semua data bersifat per event.', roles: ['SUPERADMIN', 'EVENT_OWNER'], steps: ['Periksa Current event di bawah logo Guestory.', 'Pastikan nama event tebal sesuai sebelum mengubah data, mengirim undangan, memoderasi foto, atau ekspor.', 'Jika No event selected, buat event atau periksa kepemilikan akun.'] },
  { id: 'event-setup', title: 'Buat dan publish event', summary: 'Siapkan event Draft, desain, lalu publish saat siap.', roles: ['SUPERADMIN', 'EVENT_OWNER'], steps: ['Buka Events → Create Event dan isi nama, tanggal, jenis, serta venue.', 'Atur Invitation Builder dan pilih Save & publish design.', 'Buat undangan dan QR tamu dari Guests.', 'Uji satu undangan dan QR terbaru, lalu pilih Publish.', 'Gunakan Archive setelah akses publik tidak lagi diperlukan.'] },
  { id: 'guests-invites', title: 'Tamu, undangan, dan QR', summary: 'Kelola daftar tamu dan identitas undangan personal.', roles: ['SUPERADMIN', 'EVENT_OWNER'], steps: ['Buka Guests dan pastikan Current event benar.', 'Tambah/edit tamu; nomor Indonesia otomatis dinormalisasi dari format 08…, +62…, atau 0062… menjadi 62….', 'Invite membuat atau membuka undangan—bukan mengirim WhatsApp.', 'QR membuat kode; Regen QR mencabut QR lama. Unduh SVG terbaru setelah regenerasi.'], tips: ['Delete tamu juga menghapus invitation, QR, dan attendance terkait serta tidak memiliki pemulihan dari UI.'] },
  { id: 'contact-export', title: 'Ekspor kontak dari ponsel', summary: 'Siapkan VCF dari iPhone/iCloud atau VCF/CSV dari Android dan Google Contacts.', roles: ['SUPERADMIN', 'EVENT_OWNER'], steps: ['iPhone/iCloud: buka iCloud.com/contacts, pilih kontak yang diperlukan, lalu gunakan Export vCard untuk mengunduh file .vcf.', 'Android: buka aplikasi Contacts, pilih Fix & manage/Kelola kontak → Export to file, pilih akun dan kontak, lalu simpan .vcf.', 'Google Contacts: buka contacts.google.com, pilih kontak → Export; pilih Google CSV untuk .csv atau vCard untuk .vcf.', 'Simpan file hasil ekspor di perangkat admin dan pastikan hanya kontak event yang diperlukan sebelum mengunggahnya.'], tips: ['Nama menu dapat berbeda menurut versi iOS, Android, atau merek perangkat; gunakan fungsi Export, bukan Share individual bila ingin banyak kontak.'] },
  { id: 'contact-import', title: 'Import VCF/CSV ke guest list', summary: 'Upload, tinjau, edit, pilih, dan sesuaikan hasil dengan quota paket.', roles: ['SUPERADMIN', 'EVENT_OWNER'], steps: ['Di Guests pilih Import kontak, lalu unggah file .vcf atau .csv.', 'Untuk CSV gunakan header name/nama, phone/whatsapp/wa/no_hp/nomor_hp, email, category/kategori, dan guest_count/jumlah_tamu; name serta minimal phone atau email diperlukan.', 'Tinjau preview: edit nama, WhatsApp, email, kategori, atau jumlah tamu; centang hanya baris yang ingin ditambahkan.', 'Perhatikan counter Paket, guest record terpakai, slot tersisa, dan jumlah dipilih. Pilih semua hanya memilih kontak yang muat sisa quota.', 'Perbaiki atau lepas pilihan pada baris tidak valid/duplikat, lalu pilih Tambahkan ke guest list.'], tips: ['Quota dihitung per guest record, bukan jumlah pax/guest_count.', 'Duplikat nomor atau email dideteksi terhadap guest list event dan antarbaris file; nomor Indonesia dinormalisasi ke 62….', 'Import hanya menambah guest list. Import tidak membuat atau mengirim invitation, WhatsApp, maupun QR.', 'Jika quota berubah saat preview terbuka, backend menolak seluruh import tanpa menyimpan sebagian dan halaman memperbarui sisa quota.'] },
  { id: 'builder-photos', title: 'Desain undangan dan foto', summary: 'Atur tema, susunan, slideshow desain, dan moderasi album tamu.', roles: ['SUPERADMIN', 'EVENT_OWNER'], steps: ['Di Invitation Builder pilih tema, urutkan/tampilkan bagian, dan tinjau Mobile serta Desktop.', 'Unggah maksimal 12 gambar slideshow desain, masing-masing JPG/PNG/WebP maksimal 5 MB.', 'Pilih Save & publish design untuk menerapkan desain.', 'Tamu dapat melihat album sesuai aturan undangan, tetapi baru dapat upload lewat Ambil Foto atau Pilih dari Galeri setelah check-in untuk event yang sama.', 'Di Photos, Approve menampilkan foto tamu ke album; Reject menyembunyikan; Delete menghapus permanen.'] },
  { id: 'receivers-attendance', title: 'Petugas dan attendance', summary: 'Tugaskan Receiver, pantau check-in, dan ekspor attendance.', roles: ['SUPERADMIN', 'EVENT_OWNER'], steps: ['Di Receivers, isi akun dan centang event penugasan.', 'Gunakan Resend aktivasi bila akun baru belum aktif; Cabut akses hanya menghapus assignment event tersebut.', 'Pantau Attendance dan Guest Book selama acara.', 'Gunakan Export CSV untuk attendance sesuai filter aktif.'] },
  { id: 'whatsapp-billing', title: 'WhatsApp, paket, dan kuota', summary: 'Periksa pengirim, penerima, delivery log, dan entitlement event.', roles: ['SUPERADMIN', 'EVENT_OWNER'], steps: ['Simpan nomor pengirim di Settings.', 'Uji pengiriman ke satu tamu sebelum Bulk dan periksa Delivery Log.', 'Di Plan & Billing pilih paket per event; setelah pembayaran gunakan Sync status.', 'Pastikan kuota tamu, foto, dan staff cukup sebelum event.'], tips: ['Jangan mengulang Bulk atau checkout tanpa memeriksa status kegagalan/order.'] },
  { id: 'receiver-start', title: 'Mulai shift Receiver', summary: 'Pilih assignment aktif dan siapkan perangkat.', roles: ['RECEIVER'], steps: ['Login melalui Receiver dengan akun yang sudah diaktivasi.', 'Pilih Event yang benar dan periksa venue serta jumlah checked-in.', 'Pastikan HTTPS, koneksi stabil, daya cukup, dan izin kamera aktif.', 'Logout saat shift selesai atau perangkat diserahkan.'] },
  { id: 'receiver-qr', title: 'Check-in dengan QR', summary: 'Validasi dulu, periksa identitas, baru konfirmasi.', roles: ['RECEIVER'], steps: ['Pilih Camera dan scan QR, atau tempel token/URL lalu pilih Validate.', 'Cocokkan nama, event, kategori, dan jumlah tamu.', 'Atur Hadir aktual 1–20.', 'Pilih Check-in dan tunggu pesan sukses sebelum tamu berikutnya.'], tips: ['Jangan lanjut jika event/nama tidak cocok, QR dicabut, atau tamu sudah check-in.'] },
  { id: 'receiver-manual', title: 'Check-in manual dan history', summary: 'Fallback ketika QR atau kamera tidak dapat digunakan.', roles: ['RECEIVER'], steps: ['Cari dengan nama, kode, atau nomor HP.', 'Cocokkan identitas dan guest code sebelum memilih hasil.', 'Memilih hasil langsung menyimpan check-in sesuai jumlah tercatat—tidak ada langkah konfirmasi kedua.', 'Periksa Recent Check-ins untuk memastikan transaksi tercatat dan mencegah duplikasi.'] },
  { id: 'receiver-walk-in', title: 'Walk-in guest', summary: 'Cari tamu terdaftar dahulu; buat walk-in hanya bila benar-benar baru.', roles: ['RECEIVER'], steps: ['Isi nama lalu pilih Cari tamu terdaftar dan periksa kemungkinan kecocokan.', 'Jika ada kecocokan, gunakan tamu itu untuk check-in dengan invitation yang sudah ada—jangan membuat duplikat.', 'Jika benar-benar baru, isi nama, WhatsApp opsional, jumlah hadir, kategori/relasi dan catatan opsional, lalu pilih Buat walk-in & check-in.', 'Pada layar sukses, tunjukkan QR invitation personal atau gunakan Open, Copy, dan Share. WhatsApp tidak dikirim otomatis.'], tips: ['Walk-in tetap diizinkan ketika quota paket normal penuh, tetapi dihitung dan ditandai terpisah. Jalur Add Guest dan Import tetap mengikuti quota paket.', 'Walk-in langsung memiliki record check-in, sehingga invitation-nya dapat melihat album serta memakai Ambil Foto atau Pilih dari Galeri.'] },
  { id: 'troubleshooting', title: 'Pemecahan masalah', summary: 'Langkah cepat untuk kendala yang paling umum.', roles: ['SUPERADMIN', 'EVENT_OWNER', 'RECEIVER'], steps: ['Tidak bisa login: gunakan halaman sesuai role, pastikan aktivasi selesai, atau gunakan Lupa password.', 'Data/event hilang: periksa Current event/assignment lalu Refresh.', 'QR gagal: pilih event benar, pastikan Published, gunakan QR terbaru, lalu coba Validate manual.', 'Kamera gagal: izinkan kamera di pengaturan situs, tutup aplikasi kamera lain, atau gunakan pencarian manual.', 'Link aktivasi kedaluwarsa: minta pengelola akun mengirim ulang aktivasi.'] },
  { id: 'event-day', title: 'Checklist hari event', summary: 'Pemeriksaan ringkas sebelum, selama, dan setelah operasional.', roles: ['SUPERADMIN', 'EVENT_OWNER', 'RECEIVER'], steps: ['Sebelum: akun aktif, event Published, assignment tepat, QR sampel teruji, perangkat/koneksi siap.', 'Selama: cek event aktif, verifikasi identitas dan jumlah, tunggu pesan sukses, pantau Recent/Attendance.', 'Setelah: logout perangkat bersama, ekspor bila perlu, moderasi foto, cabut assignment, dan Archive event.'] },
  { id: 'security', title: 'Keamanan dan privasi', summary: 'Lindungi akun, QR, tautan, dan data tamu.', roles: ['SUPERADMIN', 'EVENT_OWNER', 'RECEIVER'], steps: ['Jangan bagikan password, link aktivasi/reset, token, atau screenshot QR.', 'Gunakan akun masing-masing dan logout dari perangkat bersama.', 'Simpan CSV di lokasi terbatas dan hapus sesuai kebijakan retensi.', 'Periksa event sebelum Bulk, Delete, Archive, moderasi, atau ekspor.'] },
]

function useRoleGuard(apiBase: string, tokenKey: string, allowed: GuideRole[]) {
  const token = window.localStorage.getItem(tokenKey)
  const allowedRoles = allowed.join(',')
  const [state, setState] = useState<{ loading: boolean; role?: GuideRole; error?: string }>(() => token ? { loading: true } : { loading: false, error: 'Sesi diperlukan untuk membuka panduan ini.' })
  useEffect(() => {
    if (!token) return
    fetch(`${apiBase}/auth/me`, { headers: { Accept: 'application/json', Authorization: `Bearer ${token}` } })
      .then(async (response) => ({ response, profile: (await response.json().catch(() => ({}))) as { user?: Profile } }))
      .then(({ response, profile }) => {
        const role = profile.user?.role as GuideRole | undefined
        if (!response.ok || !role || !allowedRoles.split(',').includes(role)) throw new Error('Role akun tidak memiliki akses ke panduan ini.')
        setState({ loading: false, role })
      })
      .catch((error) => setState({ loading: false, error: error instanceof Error ? error.message : 'Sesi tidak dapat diverifikasi.' }))
  }, [apiBase, allowedRoles, token])
  return state
}

function GuideContent({ role }: { role: GuideRole }) {
  const [query, setQuery] = useState('')
  const visible = useMemo(() => sections.filter((section) => section.roles.includes(role)), [role])
  const filtered = useMemo(() => {
    const needle = query.trim().toLocaleLowerCase('id')
    return needle ? visible.filter((section) => [section.title, section.summary, ...section.steps, ...(section.tips ?? [])].join(' ').toLocaleLowerCase('id').includes(needle)) : visible
  }, [query, visible])
  const label = role === 'SUPERADMIN' ? 'SUPERADMIN + Event Owner' : role === 'EVENT_OWNER' ? 'Admin Event' : 'Penerima Tamu'
  return <>
    <section className="guideHero">
      <div><p className="eyebrow">Panduan sesuai akses</p><h1>User Guide</h1><p>Langkah operasional Guestory yang hanya menampilkan workflow untuk <strong>{label}</strong>.</p></div>
      <span className="guideRole"><ShieldCheck size={17} /> {label}</span>
    </section>
    <label className="guideSearch"><Search size={19} /><span className="srOnly">Cari panduan</span><input type="search" value={query} onChange={(event) => setQuery(event.target.value)} placeholder="Cari: QR, aktivasi, foto, billing…" /></label>
    <p className="guideResultCount" aria-live="polite">{filtered.length} bagian ditemukan</p>
    <section className="guideSections">
      {filtered.map((section, index) => <details className="guideSection" key={section.id} open={!query && index === 0}>
        <summary><span><strong>{section.title}</strong><small>{section.summary}</small></span><span aria-hidden="true">+</span></summary>
        <div><ol>{section.steps.map((step) => <li key={step}>{step}</li>)}</ol>{section.tips?.map((tip) => <p className="guideTip" key={tip}><strong>Catatan:</strong> {tip}</p>)}</div>
      </details>)}
      {!filtered.length && <div className="guideEmpty"><BookOpenCheck size={28} /><strong>Tidak ada hasil</strong><p>Coba kata kunci lain atau hapus pencarian.</p></div>}
    </section>
    <a className="guideFullLink" href={FULL_GUIDE_URL} target="_blank" rel="noreferrer">Buka panduan lengkap di GitHub <ExternalLink size={16} /></a>
  </>
}

function GuardMessage({ error, loginHref }: { error?: string; loginHref: string }) {
  return <section className="guideGuard"><BookOpenCheck size={32} /><h1>{error ? 'Panduan terkunci' : 'Memverifikasi sesi…'}</h1><p>{error ?? 'Kami memastikan panduan sesuai dengan role akun Anda.'}</p>{error && <a className="landingPrimary" href={loginHref}>Kembali ke login</a>}</section>
}

export function AdminHelpPage({ apiBase, Sidebar }: { apiBase: string; Sidebar: ComponentType<{ active: string }> }) {
  const auth = useRoleGuard(apiBase, 'guestory_admin_token', ['SUPERADMIN', 'EVENT_OWNER'])
  if (!auth.role) return <main className="adminCmsPage"><GuardMessage error={auth.error} loginHref="/admin" /></main>
  return <main className="adminCmsShell"><Sidebar active="/admin/help" /><section className="workspace guidePage"><GuideContent role={auth.role} /></section></main>
}

export function ReceiverHelpPage({ apiBase }: { apiBase: string }) {
  const auth = useRoleGuard(apiBase, 'guestory_receiver_token', ['RECEIVER'])
  if (!auth.role) return <main className="receiverApp"><GuardMessage error={auth.error} loginHref="/receiver" /></main>
  return <main className="receiverApp guidePage receiverGuide"><header className="receiverGuideHeader"><a href="/receiver">← Kembali ke Check-in</a></header><GuideContent role={auth.role} /></main>
}
