@extends('layouts.app')

{{-- Privacy policy (UU PDP No. 27/2022 basics). Indonesian and English text side by side in this file; the owner
     reviews it. Keep it in sync with what the code actually collects. --}}
@php $site = config('app.name'); @endphp

@section('content')
    <article class="mx-auto max-w-2xl space-y-5 text-sm leading-relaxed text-neutral-300 [&_h2]:mt-8 [&_h2]:text-base [&_h2]:font-semibold [&_h2]:text-white [&_li]:ml-5 [&_li]:list-disc">
        <h1 class="text-xl font-semibold text-white md:text-2xl">{{ __('Privacy Policy') }}</h1>
        <p class="text-xs text-neutral-500">{{ __('Last updated: :date', ['date' => $updated]) }}</p>

        @if (app()->getLocale() === 'en')
            <p>This policy explains what {{ $site }} (moviemov.id) collects, why, and how you can see or delete your data.</p>

            <h2>What we collect</h2>
            <ul>
                <li><strong>Account</strong> (only if you sign up): name, email address and a hashed password. With Google login: your name, email, Google account ID and profile photo link.</li>
                <li><strong>Watch history</strong> (logged in): which movies you opened and when.</li>
                <li><strong>Cookies</strong>: a session cookie (keeps you logged in), a cookie that counts the free movies for visitors who are not logged in, a random visitor ID for anonymous daily/monthly visitor counts, and a cookie that remembers your star rating.</li>
                <li><strong>Notifications</strong> (only if you turn them on): the address your browser gives us for sending notifications, and your language.</li>
                <li><strong>Reports</strong>: when you report a broken video, the reason and the server number. Your IP address is stored only in a scrambled (hashed) form, to stop duplicate reports and ratings.</li>
            </ul>

            <h2>Why</h2>
            <p>To run your account, let you watch without limits when logged in, send the notifications you asked for, count visitors (numbers only, no profiles), and keep the site free of spam. We do not sell your data and do not use it for advertising profiles.</p>

            <h2>Who else is involved</h2>
            <ul>
                <li><strong>Hostinger</strong> hosts the site and its database.</li>
                <li><strong>Google</strong>, if you choose Google login (Google's own privacy policy applies to your Google account).</li>
                <li><strong>Browser push services</strong> (Google, Mozilla, Apple, Microsoft) deliver notifications you turned on.</li>
                <li><strong>Video hosts</strong>: movies play in players from other sites, which have their own cookies and policies.</li>
            </ul>

            <h2>How long</h2>
            <p>Account data and watch history: until you delete your account. Anonymous visitor counts: about 13 months. Notification addresses: until you turn notifications off or your browser cancels them.</p>

            <h2>Your rights</h2>
            <p>You can see and change your name and password on your account page, and delete your account there at any time (this also deletes your watch history). Turn notifications off with the button at the bottom of any page. For anything else, such as a copy of your data, contact us.</p>
        @else
            <p>Kebijakan ini menjelaskan data apa yang dikumpulkan {{ $site }} (moviemov.id), untuk apa, dan bagaimana kamu bisa melihat atau menghapusnya.</p>

            <h2>Data yang kami kumpulkan</h2>
            <ul>
                <li><strong>Akun</strong> (hanya jika kamu mendaftar): nama, alamat email, dan password yang disimpan dalam bentuk acak (hash). Dengan login Google: nama, email, ID akun Google, dan tautan foto profil.</li>
                <li><strong>Riwayat tontonan</strong> (saat login): film yang kamu buka dan kapan.</li>
                <li><strong>Cookie</strong>: cookie sesi (agar tetap login), cookie penghitung film gratis untuk pengunjung yang belum login, ID pengunjung acak untuk menghitung jumlah pengunjung harian/bulanan secara anonim, dan cookie yang mengingat rating bintangmu.</li>
                <li><strong>Notifikasi</strong> (hanya jika kamu mengaktifkannya): alamat pengiriman notifikasi dari browsermu, dan bahasamu.</li>
                <li><strong>Laporan</strong>: saat kamu melaporkan video rusak, alasan dan nomor server. Alamat IP hanya disimpan dalam bentuk acak (hash) untuk mencegah laporan dan rating ganda.</li>
            </ul>

            <h2>Untuk apa</h2>
            <p>Untuk menjalankan akunmu, membuka tontonan tanpa batas saat login, mengirim notifikasi yang kamu minta, menghitung pengunjung (hanya angka, tanpa profil), dan menjaga situs bebas spam. Kami tidak menjual datamu dan tidak memakainya untuk profil iklan.</p>

            <h2>Pihak lain yang terlibat</h2>
            <ul>
                <li><strong>Hostinger</strong> sebagai penyedia hosting situs dan database.</li>
                <li><strong>Google</strong>, jika kamu memilih login dengan Google (kebijakan privasi Google berlaku untuk akun Google-mu).</li>
                <li><strong>Layanan push browser</strong> (Google, Mozilla, Apple, Microsoft) untuk mengirim notifikasi yang kamu aktifkan.</li>
                <li><strong>Penyedia video</strong>: film diputar lewat pemutar dari situs lain, yang memiliki cookie dan kebijakannya sendiri.</li>
            </ul>

            <h2>Berapa lama disimpan</h2>
            <p>Data akun dan riwayat tontonan: sampai kamu menghapus akun. Hitungan pengunjung anonim: sekitar 13 bulan. Alamat notifikasi: sampai kamu mematikan notifikasi atau browser membatalkannya.</p>

            <h2>Hak kamu</h2>
            <p>Sesuai UU Pelindungan Data Pribadi (UU No. 27/2022), kamu bisa melihat dan mengubah nama serta password di halaman akun, dan menghapus akun kapan saja di sana (riwayat tontonan ikut terhapus). Notifikasi bisa dimatikan lewat tombol di bagian bawah setiap halaman. Untuk permintaan lain, misalnya salinan datamu, hubungi kami.</p>
        @endif

        @if ($contact)
            <p>{{ __('Contact') }}: <a href="mailto:{{ $contact }}" class="text-white underline underline-offset-4">{{ $contact }}</a></p>
        @endif
    </article>
@endsection
