<!DOCTYPE html>
<html lang="tr" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WhatsApp Business Panel</title>
    <!-- Tailwind CSS & Google Fonts -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Chart.js for dashboard metrics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Outfit', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    <style>
        body {
            background-color: #0b0f19;
            color: #f3f4f6;
        }
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: #111827;
        }
        ::-webkit-scrollbar-thumb {
            background: #374151;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #4b5563;
        }
        /* Glassmorphism utility */
        .glass-panel {
            background: rgba(17, 24, 39, 0.7);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
    </style>
</head>
<body class="font-sans antialiased overflow-hidden h-screen flex">

    <!-- LOGIN OVERLAY -->
    <div id="login-screen" class="fixed inset-0 z-50 bg-[#090d16] flex items-center justify-center hidden">
        <div class="glass-panel w-full max-w-md p-8 rounded-2xl shadow-2xl border border-emerald-500/10 relative overflow-hidden">
            <!-- Glow background decor -->
            <div class="absolute -top-40 -left-40 w-80 h-80 bg-emerald-500/10 rounded-full blur-3xl"></div>
            <div class="absolute -bottom-40 -right-40 w-80 h-80 bg-blue-500/10 rounded-full blur-3xl"></div>

            <div class="relative z-10 text-center mb-8">
                <div class="inline-flex p-3 bg-emerald-500/10 text-emerald-400 rounded-xl mb-3">
                    <i data-lucide="message-square" class="w-8 h-8"></i>
                </div>
                <h2 class="text-2xl font-bold tracking-tight text-white">WhatsApp Business Pro</h2>
                <p class="text-gray-400 text-sm mt-1">Lütfen yönetim hesabınızla giriş yapın.</p>
            </div>

            <form id="login-form" class="space-y-4 relative z-10">
                <div id="login-error" class="hidden p-3 bg-red-500/10 border border-red-500/20 text-red-400 rounded-lg text-sm"></div>

                <div>
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">E-Posta Adresi</label>
                    <input type="email" id="login-email" required placeholder="admin@whbuspro.com" 
                        class="w-full bg-[#111827]/80 border border-gray-800 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-emerald-500/50 transition">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Şifre</label>
                    <input type="password" id="login-password" required placeholder="••••••••" 
                        class="w-full bg-[#111827]/80 border border-gray-800 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-emerald-500/50 transition">
                </div>

                <button type="submit" 
                    class="w-full bg-emerald-500 hover:bg-emerald-600 active:scale-[0.98] text-white font-semibold py-3 px-4 rounded-xl shadow-lg shadow-emerald-500/20 transition flex items-center justify-center gap-2">
                    <span>Giriş Yap</span>
                    <i data-lucide="arrow-right" class="w-4 h-4"></i>
                </button>
            </form>
        </div>
    </div>

    <!-- MAIN SIDEBAR -->
    <aside class="w-64 border-r border-gray-800 bg-[#090d16] flex flex-col justify-between shrink-0">
        <div>
            <!-- Sidebar Header / Logo -->
            <div class="p-6 border-b border-gray-800 flex items-center gap-3">
                <div class="p-2 bg-emerald-500/10 text-emerald-400 rounded-lg">
                    <i data-lucide="message-square" class="w-5 h-5"></i>
                </div>
                <div>
                    <h1 class="font-bold text-white text-base leading-none">WHBusPro</h1>
                    <span class="text-[10px] text-gray-400 tracking-widest uppercase">Messaging</span>
                </div>
            </div>

            <!-- Navigation Links -->
            <nav class="p-4 space-y-1">
                <button onclick="switchTab('dashboard')" id="nav-dashboard"
                    class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium text-emerald-400 bg-emerald-500/10 transition">
                    <i data-lucide="layout-dashboard" class="w-4 h-4"></i>
                    <span>Genel Durum</span>
                </button>

                <button onclick="switchTab('contacts')" id="nav-contacts"
                    class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium text-gray-400 hover:text-white hover:bg-gray-800/40 transition">
                    <i data-lucide="users" class="w-4 h-4"></i>
                    <span>Kişiler & Listeler</span>
                </button>

                <button onclick="switchTab('templates')" id="nav-templates"
                    class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium text-gray-400 hover:text-white hover:bg-gray-800/40 transition">
                    <i data-lucide="file-text" class="w-4 h-4"></i>
                    <span>Şablonlar</span>
                </button>

                <button onclick="switchTab('campaigns')" id="nav-campaigns"
                    class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium text-gray-400 hover:text-white hover:bg-gray-800/40 transition">
                    <i data-lucide="send" class="w-4 h-4"></i>
                    <span>Kampanyalar</span>
                </button>

                <button onclick="switchTab('settings')" id="nav-settings"
                    class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium text-gray-400 hover:text-white hover:bg-gray-800/40 transition">
                    <i data-lucide="settings" class="w-4 h-4"></i>
                    <span>Ayarlar</span>
                </button>
            </nav>
        </div>

        <!-- User profile block at bottom -->
        <div class="p-4 border-t border-gray-800 flex items-center justify-between">
            <div class="flex items-center gap-3 overflow-hidden">
                <div class="w-10 h-10 bg-emerald-500/10 rounded-full flex items-center justify-center font-bold text-emerald-400 text-sm capitalize" id="user-avatar">
                    A
                </div>
                <div class="overflow-hidden">
                    <h4 class="font-semibold text-sm text-white truncate" id="user-name">Yönetici</h4>
                    <span class="text-xs text-gray-400 capitalize" id="user-role">Admin</span>
                </div>
            </div>
            <button onclick="logout()" class="p-2 text-gray-400 hover:text-red-400 rounded-lg hover:bg-red-500/10 transition">
                <i data-lucide="log-out" class="w-4 h-4"></i>
            </button>
        </div>
    </aside>

    <!-- MAIN BODY -->
    <main class="flex-1 flex flex-col overflow-hidden">

        <!-- HEADER -->
        <header class="h-16 border-b border-gray-800 bg-[#090d16] flex items-center justify-between px-8 shrink-0">
            <h2 class="font-bold text-lg text-white" id="page-title">Genel Durum</h2>
            <div class="flex items-center gap-4">
                <!-- Status Badge -->
                <div class="flex items-center gap-2 px-3 py-1.5 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs">
                    <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span>Meta Cloud API: Aktif</span>
                </div>
            </div>
        </header>

        <!-- CONTAINER FOR TABS -->
        <div class="flex-1 overflow-y-auto p-8 relative">

            <!-- TAB 1: DASHBOARD -->
            <section id="tab-dashboard" class="space-y-6">
                <!-- Status Widget Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div class="glass-panel p-6 rounded-2xl relative overflow-hidden">
                        <div class="text-gray-400 text-xs font-semibold uppercase tracking-wider">Hesap Kalitesi</div>
                        <div class="text-2xl font-bold mt-2 text-emerald-400" id="stat-account-quality">GREEN</div>
                        <div class="text-[10px] text-gray-400 mt-1">Sorunsuz çalışma durumu</div>
                    </div>
                    <div class="glass-panel p-6 rounded-2xl relative overflow-hidden">
                        <div class="text-gray-400 text-xs font-semibold uppercase tracking-wider">Gönderilen Mesaj</div>
                        <div class="text-2xl font-bold mt-2 text-white" id="stat-messages-sent">0</div>
                        <div class="text-[10px] text-emerald-400 mt-1" id="stat-delivered-rate">%0 Teslim edildi</div>
                    </div>
                    <div class="glass-panel p-6 rounded-2xl relative overflow-hidden">
                        <div class="text-gray-400 text-xs font-semibold uppercase tracking-wider">Okunma Oranı</div>
                        <div class="text-2xl font-bold mt-2 text-blue-400" id="stat-read-rate">%0</div>
                        <div class="text-[10px] text-gray-400 mt-1">Okundu bilgisi dönenler</div>
                    </div>
                    <div class="glass-panel p-6 rounded-2xl relative overflow-hidden">
                        <div class="text-gray-400 text-xs font-semibold uppercase tracking-wider">Hata Oranı</div>
                        <div class="text-2xl font-bold mt-2 text-red-400" id="stat-failed-rate">%0</div>
                        <div class="text-[10px] text-gray-400 mt-1">Gönderimi başarısız olanlar</div>
                    </div>
                </div>

                <!-- Live monitoring charts -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="glass-panel p-6 rounded-2xl lg:col-span-2">
                        <h3 class="font-bold text-sm text-gray-300 mb-4">Mesaj Gönderim Performansı</h3>
                        <div class="h-64">
                            <canvas id="messagesChart"></canvas>
                        </div>
                    </div>
                    <div class="glass-panel p-6 rounded-2xl">
                        <h3 class="font-bold text-sm text-gray-300 mb-4">Meta Durumu</h3>
                        <div class="space-y-4">
                            <div class="flex justify-between items-center pb-3 border-b border-gray-800">
                                <span class="text-sm text-gray-400">Numara Durumu</span>
                                <span class="text-sm font-semibold text-emerald-400" id="stat-phone-status">CONNECTED</span>
                            </div>
                            <div class="flex justify-between items-center pb-3 border-b border-gray-800">
                                <span class="text-sm text-gray-400">Günlük Limit</span>
                                <span class="text-sm font-semibold text-white">1,000 Mesaj</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-gray-400">Kuyruk Durumu (Redis)</span>
                                <span class="text-sm font-semibold text-blue-400">Aktif (Worker Dinlemede)</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- TAB 2: CONTACTS & LISTS -->
            <section id="tab-contacts" class="space-y-6 hidden">
                <div class="flex justify-between items-center">
                    <h3 class="font-bold text-gray-300 text-lg">Kişi ve Segmentasyon Yönetimi</h3>
                    <div class="flex gap-3">
                        <button onclick="openModal('modal-list')" class="bg-gray-800 hover:bg-gray-700 text-white font-medium py-2.5 px-4 rounded-xl text-sm transition flex items-center gap-2">
                            <i data-lucide="plus" class="w-4 h-4"></i>
                            <span>Yeni Liste</span>
                        </button>
                        <button onclick="openModal('modal-import')" class="bg-emerald-500 hover:bg-emerald-600 text-white font-medium py-2.5 px-4 rounded-xl text-sm transition flex items-center gap-2">
                            <i data-lucide="upload" class="w-4 h-4"></i>
                            <span>Kişi İçe Aktar (CSV)</span>
                        </button>
                    </div>
                </div>

                <!-- Grid layout for lists and list members -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Left: Lists Table -->
                    <div class="glass-panel p-6 rounded-2xl lg:col-span-1">
                        <h4 class="font-semibold text-sm text-gray-400 mb-4">Kişi Listeleri</h4>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead>
                                    <tr class="text-xs text-gray-400 border-b border-gray-800">
                                        <th class="pb-3">Grup Adı</th>
                                        <th class="pb-3 text-right">Kişi Sayısı</th>
                                    </tr>
                                </thead>
                                <tbody id="lists-table-body" class="divide-y divide-gray-800 text-sm">
                                    <!-- Dynamic content -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Right: Contacts Paginated Table -->
                    <div class="glass-panel p-6 rounded-2xl lg:col-span-2 space-y-4">
                        <div class="flex justify-between items-center">
                            <h4 class="font-semibold text-sm text-gray-400">Tüm Kayıtlı Kişiler</h4>
                            <input type="text" id="contact-search" placeholder="İsim veya telefon ara..." oninput="fetchContacts()" 
                                class="bg-[#111827]/80 border border-gray-800 rounded-xl px-4 py-2 text-sm text-white focus:outline-none focus:border-emerald-500/50 w-64 transition">
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead>
                                    <tr class="text-xs text-gray-400 border-b border-gray-800">
                                        <th class="pb-3">Telefon</th>
                                        <th class="pb-3">Ad Soyad</th>
                                        <th class="pb-3">Onay (Opt-in)</th>
                                        <th class="pb-3">Statü</th>
                                    </tr>
                                </thead>
                                <tbody id="contacts-table-body" class="divide-y divide-gray-800 text-sm">
                                    <!-- Dynamic content -->
                                </tbody>
                            </table>
                        </div>
                        <!-- Pagination controls -->
                        <div class="flex justify-between items-center pt-2">
                            <span class="text-xs text-gray-400" id="contacts-count">Gösteriliyor: 0</span>
                            <div class="flex gap-2">
                                <button onclick="changeContactPage(-1)" id="btn-contacts-prev" class="p-2 rounded-lg bg-gray-800 hover:bg-gray-700 text-gray-400 disabled:opacity-40" disabled>
                                    <i data-lucide="chevron-left" class="w-4 h-4"></i>
                                </button>
                                <button onclick="changeContactPage(1)" id="btn-contacts-next" class="p-2 rounded-lg bg-gray-800 hover:bg-gray-700 text-gray-400 disabled:opacity-40" disabled>
                                    <i data-lucide="chevron-right" class="w-4 h-4"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- TAB 3: TEMPLATES -->
            <section id="tab-templates" class="space-y-6 hidden">
                <div class="flex justify-between items-center">
                    <h3 class="font-bold text-gray-300 text-lg">Meta Onaylı Şablonlar</h3>
                    <button onclick="syncTemplates()" id="btn-sync-templates" class="bg-emerald-500 hover:bg-emerald-600 text-white font-medium py-2.5 px-4 rounded-xl text-sm transition flex items-center gap-2">
                        <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                        <span>Meta Şablonlarını Eşitle</span>
                    </button>
                </div>

                <div class="glass-panel p-6 rounded-2xl">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead>
                                <tr class="text-xs text-gray-400 border-b border-gray-800">
                                    <th class="pb-3">Şablon Adı</th>
                                    <th class="pb-3">Kategori</th>
                                    <th class="pb-3">Dil</th>
                                    <th class="pb-3">Değişken Sayısı</th>
                                    <th class="pb-3">Meta Durumu</th>
                                </tr>
                            </thead>
                            <tbody id="templates-table-body" class="divide-y divide-gray-800 text-sm">
                                <!-- Dynamic content -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- TAB 4: CAMPAIGNS -->
            <section id="tab-campaigns" class="space-y-6 hidden">
                <div class="flex justify-between items-center">
                    <h3 class="font-bold text-gray-300 text-lg">Toplu Mesaj Kampanyaları</h3>
                    <button onclick="openModal('modal-campaign')" class="bg-emerald-500 hover:bg-emerald-600 text-white font-medium py-2.5 px-4 rounded-xl text-sm transition flex items-center gap-2">
                        <i data-lucide="plus" class="w-4 h-4"></i>
                        <span>Yeni Kampanya Oluştur</span>
                    </button>
                </div>

                <!-- Campaigns lists -->
                <div class="space-y-4" id="campaigns-list-container">
                    <!-- Dynamic campaign cards go here -->
                </div>
            </section>

            <!-- TAB 5: SETTINGS -->
            <section id="tab-settings" class="space-y-6 hidden">
                <h3 class="font-bold text-gray-300 text-lg">Meta API Bağlantı Ayarları</h3>
                
                <div class="glass-panel p-8 rounded-2xl border border-emerald-500/10">
                    <form id="form-settings-whatsapp" class="space-y-6">
                        <div id="settings-success" class="hidden p-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-xl text-sm font-semibold">
                            Ayarlar başarıyla güncellendi!
                        </div>
                        <div id="settings-error" class="hidden p-4 bg-red-500/10 border border-red-500/20 text-red-400 rounded-xl text-sm font-semibold"></div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Meta API URL</label>
                                <input type="url" id="setting-api-url" required
                                    class="w-full bg-[#111827]/80 border border-gray-800 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-emerald-500/50 transition">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">API Sürümü (Version)</label>
                                <input type="text" id="setting-api-version" required
                                    class="w-full bg-[#111827]/80 border border-gray-800 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-emerald-500/50 transition">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">WhatsApp Telefon Numarası ID (Phone Number ID)</label>
                                <input type="text" id="setting-phone-id" required
                                    class="w-full bg-[#111827]/80 border border-gray-800 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-emerald-500/50 transition">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">WhatsApp İşletme Hesabı ID (Business Account ID)</label>
                                <input type="text" id="setting-waba-id" required
                                    class="w-full bg-[#111827]/80 border border-gray-800 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-emerald-500/50 transition">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Kalıcı Erişim Anahtarı (Permanent Access Token)</label>
                            <textarea id="setting-token" required rows="3"
                                class="w-full bg-[#111827]/80 border border-gray-800 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-emerald-500/50 transition"></textarea>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Uygulama Gizli Anahtarı (App Secret)</label>
                                <input type="password" id="setting-app-secret" required
                                    class="w-full bg-[#111827]/80 border border-gray-800 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-emerald-500/50 transition">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Webhook Doğrulama Anahtarı (Verify Token)</label>
                                <input type="text" id="setting-verify-token" required
                                    class="w-full bg-[#111827]/80 border border-gray-800 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-emerald-500/50 transition">
                            </div>
                        </div>

                        <div class="flex justify-end pt-2">
                            <button type="submit" class="bg-emerald-500 hover:bg-emerald-600 text-white font-semibold py-3 px-6 rounded-xl text-sm transition flex items-center gap-2">
                                <i data-lucide="check" class="w-4 h-4"></i>
                                <span>Ayarları Güncelle</span>
                            </button>
                        </div>
                    </form>
                </div>
            </section>

        </div>
    </main>

    <!-- MODAL: NEW LIST -->
    <div id="modal-list" class="fixed inset-0 z-40 bg-black/60 backdrop-blur-sm flex items-center justify-center hidden">
        <div class="glass-panel w-full max-w-md p-8 rounded-2xl shadow-2xl relative">
            <h3 class="text-lg font-bold text-white mb-4">Yeni Kişi Grubu Oluştur</h3>
            <form id="form-create-list" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Grup Adı</label>
                    <input type="text" id="list-name" required placeholder="örn: Yaz Kampanyası Müşterileri" 
                        class="w-full bg-[#111827]/80 border border-gray-800 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-emerald-500/50 transition">
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="closeModal('modal-list')" class="bg-gray-800 text-gray-300 px-4 py-2.5 rounded-xl text-sm hover:bg-gray-700">İptal</button>
                    <button type="submit" class="bg-emerald-500 text-white px-5 py-2.5 rounded-xl text-sm hover:bg-emerald-600 font-semibold">Oluştur</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: CSV IMPORT -->
    <div id="modal-import" class="fixed inset-0 z-40 bg-black/60 backdrop-blur-sm flex items-center justify-center hidden">
        <div class="glass-panel w-full max-w-md p-8 rounded-2xl shadow-2xl relative">
            <h3 class="text-lg font-bold text-white mb-4">Kişileri İçe Aktar (CSV)</h3>
            <form id="form-import-contacts" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Hedef Liste</label>
                    <select id="import-list-select" required 
                        class="w-full bg-[#111827]/80 border border-gray-800 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-emerald-500/50 transition">
                        <!-- Dynamic options -->
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Varsayılan Ülke Kodu</label>
                    <input type="text" id="import-country-code" value="+90" required
                        class="w-full bg-[#111827]/80 border border-gray-800 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-emerald-500/50 transition">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">CSV Dosyası</label>
                    <input type="file" id="import-file" required accept=".csv,.txt"
                        class="w-full text-sm text-gray-400 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-sm file:font-semibold file:bg-emerald-500/10 file:text-emerald-400 hover:file:bg-emerald-500/20">
                    <p class="text-[10px] text-gray-400 mt-2">Dosya formatı virgülle ayrılmış (CSV) olmalı ve `phone_number`, `opted_in` (1/0) sütunlarını içermelidir.</p>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="closeModal('modal-import')" class="bg-gray-800 text-gray-300 px-4 py-2.5 rounded-xl text-sm hover:bg-gray-700">İptal</button>
                    <button type="submit" class="bg-emerald-500 text-white px-5 py-2.5 rounded-xl text-sm hover:bg-emerald-600 font-semibold flex items-center gap-2">
                        <span>Yükle</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: CREATE CAMPAIGN -->
    <div id="modal-campaign" class="fixed inset-0 z-40 bg-black/60 backdrop-blur-sm flex items-center justify-center hidden">
        <div class="glass-panel w-full max-w-lg p-8 rounded-2xl shadow-2xl relative">
            <h3 class="text-lg font-bold text-white mb-4">Yeni Kampanya Tanımla</h3>
            <form id="form-create-campaign" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Kampanya Adı</label>
                    <input type="text" id="campaign-name" required placeholder="örn: Temmuz İndirimi Duyurusu" 
                        class="w-full bg-[#111827]/80 border border-gray-800 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-emerald-500/50 transition">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Gönderim Yapılacak Liste</label>
                        <select id="campaign-list-select" required
                            class="w-full bg-[#111827]/80 border border-gray-800 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-emerald-500/50 transition">
                            <!-- Dynamic -->
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Mesaj Şablonu (Meta)</label>
                        <select id="campaign-template-select" required onchange="renderTemplateFields()"
                            class="w-full bg-[#111827]/80 border border-gray-800 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-emerald-500/50 transition">
                            <!-- Dynamic -->
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Dakikalık Hız Sınırı (Throttle)</label>
                        <input type="number" id="campaign-throttle" value="60" required min="1"
                            class="w-full bg-[#111827]/80 border border-gray-800 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-emerald-500/50 transition">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-400 uppercase tracking-wider mb-2">Gönderim Zamanı (Opsiyonel)</label>
                        <input type="datetime-local" id="campaign-schedule"
                            class="w-full bg-[#111827]/80 border border-gray-800 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-emerald-500/50 transition">
                    </div>
                </div>

                <!-- Dynamic inputs for variables (Rendered via JS) -->
                <div id="dynamic-variables-container" class="space-y-3 hidden">
                    <label class="block text-xs font-semibold text-gray-300 uppercase tracking-wider">Şablon Değişkenleri</label>
                    <div id="dynamic-inputs" class="grid grid-cols-1 md:grid-cols-2 gap-3"></div>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="closeModal('modal-campaign')" class="bg-gray-800 text-gray-300 px-4 py-2.5 rounded-xl text-sm hover:bg-gray-700">İptal</button>
                    <button type="submit" class="bg-emerald-500 text-white px-5 py-2.5 rounded-xl text-sm hover:bg-emerald-600 font-semibold flex items-center gap-2">
                        <i data-lucide="check" class="w-4 h-4"></i>
                        <span>Oluştur</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: DETAILED ERRORS -->
    <div id="modal-errors" class="fixed inset-0 z-40 bg-black/60 backdrop-blur-sm flex items-center justify-center hidden">
        <div class="glass-panel w-full max-w-2xl p-8 rounded-2xl shadow-2xl relative max-h-[80vh] flex flex-col">
            <h3 class="text-lg font-bold text-white mb-4">Kampanya Hata Logları</h3>
            <div class="overflow-y-auto flex-1 divide-y divide-gray-800 text-sm pr-2" id="error-logs-container">
                <!-- Dynamic errors list -->
            </div>
            <div class="flex justify-end gap-3 pt-4 border-t border-gray-800 mt-4 shrink-0">
                <button type="button" onclick="closeModal('modal-errors')" class="bg-gray-800 text-gray-300 px-4 py-2.5 rounded-xl text-sm hover:bg-gray-700">Kapat</button>
            </div>
        </div>
    </div>

    <!-- SPA logic and API interaction JavaScript -->
    <script>
        const API_URL = '/api';
        let currentTab = 'dashboard';
        let contactsPage = 1;
        let chartInstance = null;

        // Check token on launch
        document.addEventListener('DOMContentLoaded', () => {
            lucide.createIcons();
            initChart();
            
            if (!getToken()) {
                showLogin();
            } else {
                fetchUserData();
                loadTab('dashboard');
            }

            // Polling for campaign updates every 5 seconds
            setInterval(() => {
                if (currentTab === 'campaigns') {
                    fetchCampaigns();
                } else if (currentTab === 'dashboard') {
                    fetchDashboardStats();
                }
            }, 5000);
        });

        // Auth Helper Functions
        function getToken() { return localStorage.getItem('token'); }
        function saveToken(token) { localStorage.setItem('token', token); }
        function clearToken() { localStorage.removeItem('token'); }

        function showLogin() {
            document.getElementById('login-screen').classList.remove('hidden');
        }
        function hideLogin() {
            document.getElementById('login-screen').classList.add('hidden');
        }

        // Login Handler
        document.getElementById('login-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            const email = document.getElementById('login-email').value;
            const password = document.getElementById('login-password').value;
            const errorDiv = document.getElementById('login-error');

            try {
                const response = await fetch(`${API_URL}/auth/login`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email, password })
                });

                const data = await response.json();
                if (response.ok) {
                    saveToken(data.access_token);
                    hideLogin();
                    fetchUserData();
                    loadTab('dashboard');
                } else {
                    errorDiv.textContent = data.message || 'Giriş yapılamadı.';
                    errorDiv.classList.remove('hidden');
                }
            } catch (err) {
                errorDiv.textContent = 'Sunucuyla bağlantı kurulamadı.';
                errorDiv.classList.remove('hidden');
            }
        });

        // Logout
        async function logout() {
            try {
                await makeRequest('/auth/logout', 'POST');
            } catch(e){}
            clearToken();
            showLogin();
        }

        // Fetch user metadata
        async function fetchUserData() {
            try {
                const user = await makeRequest('/auth/me');
                document.getElementById('user-name').textContent = user.name;
                document.getElementById('user-role').textContent = user.role;
                document.getElementById('user-avatar').textContent = user.name.charAt(0);
            } catch (err) {
                logout();
            }
        }

        // Request wrapper with auth headers
        async function makeRequest(endpoint, method = 'GET', body = null) {
            const token = getToken();
            const headers = {
                'Authorization': `Bearer ${token}`,
                'Accept': 'application/json'
            };
            if (body && !(body instanceof FormData)) {
                headers['Content-Type'] = 'application/json';
            }

            const response = await fetch(`${API_URL}${endpoint}`, {
                method,
                headers,
                body: body instanceof FormData ? body : (body ? JSON.stringify(body) : null)
            });

            if (response.status === 401) {
                if (endpoint !== '/auth/logout' && endpoint !== '/auth/login') {
                    logout();
                }
                throw new Error('Unauthorized');
            }

            if (!response.ok) {
                const errData = await response.json();
                throw new Error(errData.message || 'API request failed');
            }

            return response.json();
        }

        // Switch Tabs (navigation)
        function switchTab(tabId) {
            // Update nav active styles
            ['dashboard', 'contacts', 'templates', 'campaigns', 'settings'].forEach(t => {
                const btn = document.getElementById(`nav-${t}`);
                if (btn) {
                    if (t === tabId) {
                        btn.className = "w-full flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium text-emerald-400 bg-emerald-500/10 transition";
                    } else {
                        btn.className = "w-full flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-medium text-gray-400 hover:text-white hover:bg-gray-800/40 transition";
                    }
                }
            });

            // Toggle sections visibility
            ['dashboard', 'contacts', 'templates', 'campaigns', 'settings'].forEach(t => {
                const sec = document.getElementById(`tab-${t}`);
                if (sec) sec.classList.add('hidden');
            });
            const activeSec = document.getElementById(`tab-${tabId}`);
            if (activeSec) activeSec.classList.remove('hidden');

            const pageTitles = {
                'dashboard': 'Genel Durum',
                'contacts': 'Kişiler & Listeler',
                'templates': 'Mesaj Şablonları',
                'campaigns': 'Kampanya Yönetimi',
                'settings': 'Bağlantı Ayarları'
            };
            document.getElementById('page-title').textContent = pageTitles[tabId];
            
            currentTab = tabId;
            loadTab(tabId);
        }

        // Tab Loader
        function loadTab(tabId) {
            if (tabId === 'dashboard') {
                fetchDashboardStats();
            } else if (tabId === 'contacts') {
                contactsPage = 1;
                fetchContacts();
                fetchLists();
            } else if (tabId === 'templates') {
                fetchTemplates();
            } else if (tabId === 'campaigns') {
                fetchCampaigns();
            } else if (tabId === 'settings') {
                fetchSettings();
            }
        }

        // Initialize Chart.js
        function initChart() {
            const ctx = document.getElementById('messagesChart').getContext('2d');
            chartInstance = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Gönderilen', 'İletilen', 'Okunan', 'Hata'],
                    datasets: [{
                        label: 'Mesaj Adeti',
                        data: [0, 0, 0, 0],
                        backgroundColor: [
                            'rgba(16, 185, 129, 0.2)', // sent
                            'rgba(59, 130, 246, 0.2)', // delivered
                            'rgba(139, 92, 246, 0.2)', // read
                            'rgba(239, 68, 68, 0.2)'   // failed
                        ],
                        borderColor: [
                            '#10b981',
                            '#3b82f6',
                            '#8b5cf6',
                            '#ef4444'
                        ],
                        borderWidth: 1.5,
                        borderRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: 'rgba(255, 255, 255, 0.05)' },
                            ticks: { color: '#9ca3af' }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { color: '#9ca3af' }
                        }
                    }
                }
            });
        }

        // Dashboard statistics fetcher
        async function fetchDashboardStats() {
            try {
                // Get summary of all campaigns
                const response = await makeRequest('/campaigns');
                const campaigns = response.data || [];
                
                let totals = { queued: 0, sent: 0, delivered: 0, read: 0, failed: 0, total: 0 };
                campaigns.forEach(c => {
                    totals.queued += c.queued_count || 0;
                    totals.sent += c.sent_count || 0;
                    totals.delivered += c.delivered_count || 0;
                    totals.read += c.read_count || 0;
                    totals.failed += c.failed_count || 0;
                    totals.total += c.total_count || 0;
                });

                document.getElementById('stat-messages-sent').textContent = totals.sent + totals.delivered + totals.read;
                
                const delRate = totals.total > 0 ? Math.round(((totals.delivered + totals.read) / totals.total) * 100) : 0;
                document.getElementById('stat-delivered-rate').textContent = `%${delRate} İletildi`;

                const readRate = totals.total > 0 ? Math.round((totals.read / totals.total) * 100) : 0;
                document.getElementById('stat-read-rate').textContent = `%${readRate}`;

                const failRate = totals.total > 0 ? Math.round((totals.failed / totals.total) * 100) : 0;
                document.getElementById('stat-failed-rate').textContent = `%${failRate}`;

                // Update charts
                chartInstance.data.datasets[0].data = [
                    totals.sent,
                    totals.delivered,
                    totals.read,
                    totals.failed
                ];
                chartInstance.update();
            } catch(e){}
        }

        // Contacts & Lists Management
        async function fetchContacts() {
            try {
                const search = document.getElementById('contact-search').value;
                const data = await makeRequest(`/contacts?page=${contactsPage}&search=${search}`);
                const tbody = document.getElementById('contacts-table-body');
                tbody.innerHTML = '';

                data.data.forEach(c => {
                    const statusColors = {
                        'active': 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                        'blocked': 'bg-red-500/10 text-red-400 border-red-500/20',
                        'invalid': 'bg-yellow-500/10 text-yellow-400 border-yellow-500/20'
                    };
                    const statusColor = statusColors[c.status] || 'bg-gray-500/10 text-gray-400 border-gray-500/20';

                    tbody.innerHTML += `
                        <tr>
                            <td class="py-3.5 font-mono text-white">${c.phone_number}</td>
                            <td class="py-3.5 text-gray-300">${c.full_name || '-'}</td>
                            <td class="py-3.5">
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold ${c.opted_in ? 'bg-emerald-500/10 text-emerald-400' : 'bg-red-500/10 text-red-400'}">
                                    ${c.opted_in ? 'Onaylı' : 'İzinsiz'}
                                </span>
                            </td>
                            <td class="py-3.5">
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-medium border ${statusColor} capitalize">
                                    ${c.status}
                                </span>
                            </td>
                        </tr>
                    `;
                });

                document.getElementById('contacts-count').textContent = `Gösteriliyor: ${data.from || 0}-${data.to || 0} / Toplam: ${data.total}`;
                
                document.getElementById('btn-contacts-prev').disabled = !data.prev_page_url;
                document.getElementById('btn-contacts-next').disabled = !data.next_page_url;
            } catch(e){}
        }

        function changeContactPage(direction) {
            contactsPage += direction;
            fetchContacts();
        }

        async function fetchLists() {
            try {
                const lists = await makeRequest('/contact-lists');
                const tbody = document.getElementById('lists-table-body');
                tbody.innerHTML = '';

                // Populate imports and campaign selection modals
                const importSelect = document.getElementById('import-list-select');
                const campaignSelect = document.getElementById('campaign-list-select');
                importSelect.innerHTML = '';
                campaignSelect.innerHTML = '';

                lists.forEach(l => {
                    tbody.innerHTML += `
                        <tr>
                            <td class="py-3.5 text-white font-semibold">${l.name}</td>
                            <td class="py-3.5 text-right font-mono text-gray-300">${l.contacts_count} kişi</td>
                        </tr>
                    `;

                    importSelect.innerHTML += `<option value="${l.id}">${l.name}</option>`;
                    campaignSelect.innerHTML += `<option value="${l.id}">${l.name}</option>`;
                });
            } catch(e){}
        }

        // List Create Form handler
        document.getElementById('form-create-list').addEventListener('submit', async (e) => {
            e.preventDefault();
            const name = document.getElementById('list-name').value;
            try {
                await makeRequest('/contact-lists', 'POST', { name });
                closeModal('modal-list');
                document.getElementById('list-name').value = '';
                fetchLists();
            } catch(err) {
                alert('Liste oluşturulamadı: ' + err.message);
            }
        });

        // CSV Import Handler
        document.getElementById('form-import-contacts').addEventListener('submit', async (e) => {
            e.preventDefault();
            const listId = document.getElementById('import-list-select').value;
            const countryCode = document.getElementById('import-country-code').value;
            const fileInput = document.getElementById('import-file');

            const formData = new FormData();
            formData.append('list_id', listId);
            formData.append('default_country_code', countryCode);
            formData.append('file', fileInput.files[0]);

            try {
                const res = await makeRequest('/contacts/import', 'POST', formData);
                alert(`İçe aktarım tamamlandı!\nEklenen: ${res.data.imported}\nGüncellenen: ${res.data.updated}\nHatalı: ${res.data.failed}`);
                closeModal('modal-import');
                fileInput.value = '';
                fetchContacts();
                fetchLists();
            } catch(err) {
                alert('Hata: ' + err.message);
            }
        });

        // Templates Sync and display
        async function fetchTemplates() {
            try {
                const templates = await makeRequest('/templates');
                const tbody = document.getElementById('templates-table-body');
                tbody.innerHTML = '';

                const campaignTemplateSelect = document.getElementById('campaign-template-select');
                campaignTemplateSelect.innerHTML = '<option value="">Şablon Seçin</option>';

                templates.forEach(t => {
                    tbody.innerHTML += `
                        <tr>
                            <td class="py-3.5 text-white font-semibold">${t.meta_template_name}</td>
                            <td class="py-3.5 text-gray-400 text-xs">${t.category || '-'}</td>
                            <td class="py-3.5 font-mono text-gray-300">${t.language_code}</td>
                            <td class="py-3.5 font-mono text-center text-emerald-400">${t.body_variables_count} değişken</td>
                            <td class="py-3.5">
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold ${t.status === 'APPROVED' ? 'bg-emerald-500/10 text-emerald-400' : 'bg-yellow-500/10 text-yellow-400'}">
                                    ${t.status}
                                </span>
                            </td>
                        </tr>
                    `;

                    if (t.status === 'APPROVED') {
                        campaignTemplateSelect.innerHTML += `<option value="${t.id}" data-vars="${t.body_variables_count}">${t.meta_template_name}</option>`;
                    }
                });
            } catch(e){}
        }

        async function syncTemplates() {
            const btn = document.getElementById('btn-sync-templates');
            btn.disabled = true;
            btn.innerHTML = `<i data-lucide="refresh-cw" class="w-4 h-4 animate-spin"></i><span>Senkronize Ediliyor...</span>`;
            lucide.createIcons();

            try {
                const res = await makeRequest('/templates/sync', 'POST');
                alert(res.message);
                fetchTemplates();
            } catch (err) {
                alert('Senkronizasyon hatası: ' + err.message);
            } finally {
                btn.disabled = false;
                btn.innerHTML = `<i data-lucide="refresh-cw" class="w-4 h-4"></i><span>Meta Şablonlarını Eşitle</span>`;
                lucide.createIcons();
            }
        }

        // Campaigns Management
        async function fetchCampaigns() {
            try {
                const response = await makeRequest('/campaigns');
                const campaigns = response.data || [];
                const container = document.getElementById('campaigns-list-container');
                
                if (campaigns.length === 0) {
                    container.innerHTML = `
                        <div class="glass-panel p-8 text-center text-gray-400 rounded-2xl">
                            Henüz oluşturulmuş kampanya bulunmuyor.
                        </div>
                    `;
                    return;
                }

                container.innerHTML = '';
                campaigns.forEach(c => {
                    const total = c.total_count || 0;
                    const sent = (c.sent_count || 0) + (c.delivered_count || 0) + (c.read_count || 0);
                    const progress = total > 0 ? Math.round((sent / total) * 100) : 0;
                    const failed = c.failed_count || 0;

                    const statusLabels = {
                        'draft': 'Taslak',
                        'queued': 'Kuyrukta',
                        'sending': 'Gönderiliyor',
                        'completed': 'Tamamlandı',
                        'failed': 'Başarısız',
                        'paused': 'Duraklatıldı'
                    };
                    const statusColors = {
                        'draft': 'bg-gray-800 text-gray-400 border-gray-700',
                        'queued': 'bg-blue-500/10 text-blue-400 border-blue-500/20',
                        'sending': 'bg-yellow-500/10 text-yellow-400 border-yellow-500/20',
                        'completed': 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
                        'failed': 'bg-red-500/10 text-red-400 border-red-500/20',
                        'paused': 'bg-amber-500/10 text-amber-400 border-amber-500/20'
                    };

                    const statusText = statusLabels[c.status] || c.status;
                    const statusClass = statusColors[c.status] || 'bg-gray-800 text-gray-300';

                    let actionsHtml = '';
                    if (c.status === 'draft') {
                        actionsHtml = `
                            <button onclick="triggerCampaign(${c.id})" class="bg-emerald-500 hover:bg-emerald-600 text-white font-medium py-1.5 px-3 rounded-lg text-xs transition flex items-center gap-1.5">
                                <i data-lucide="play" class="w-3.5 h-3.5"></i>
                                <span>Gönderimi Başlat</span>
                            </button>
                        `;
                    }
                    if (failed > 0) {
                        actionsHtml += `
                            <button onclick="showCampaignErrors(${c.id})" class="bg-red-500/10 hover:bg-red-500/20 text-red-400 font-medium py-1.5 px-3 rounded-lg text-xs border border-red-500/20 transition flex items-center gap-1.5">
                                <i data-lucide="alert-circle" class="w-3.5 h-3.5"></i>
                                <span>Hata Raporu (${failed})</span>
                            </button>
                        `;
                    }

                    container.innerHTML += `
                        <div class="glass-panel p-6 rounded-2xl space-y-4">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h4 class="font-bold text-white text-base">${c.name}</h4>
                                    <p class="text-xs text-gray-400 mt-1">Şablon: <span class="text-gray-300 font-medium">${c.template?.meta_template_name}</span> | Hedef: <span class="text-gray-300 font-medium">${c.list?.name}</span></p>
                                </div>
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold border ${statusClass}">
                                    ${statusText}
                                </span>
                            </div>

                            <!-- Progress Bar -->
                            ${c.status !== 'draft' ? `
                                <div class="space-y-1">
                                    <div class="flex justify-between text-xs text-gray-400 font-mono">
                                        <span>Giden: ${sent} / Toplam: ${total}</span>
                                        <span>%${progress}</span>
                                    </div>
                                    <div class="w-full bg-gray-800 h-2 rounded-full overflow-hidden">
                                        <div class="bg-emerald-500 h-full transition-all duration-500" style="width: ${progress}%"></div>
                                    </div>
                                </div>
                            ` : ''}

                            <div class="flex justify-between items-center pt-2">
                                <div class="text-xs text-gray-400">
                                    Limit: <span class="text-gray-300 font-medium font-mono">${c.throttle_per_minute}/dk</span>
                                    ${c.scheduled_at ? ` | Zamanlama: <span class="text-gray-300 font-medium">${new Date(c.scheduled_at).toLocaleString('tr-TR')}</span>` : ''}
                                </div>
                                <div class="flex gap-2">
                                    ${actionsHtml}
                                </div>
                            </div>
                        </div>
                    `;
                });
                lucide.createIcons();
            } catch(e){}
        }

        // Render dynamic variables fields based on selected template
        function renderTemplateFields() {
            const select = document.getElementById('campaign-template-select');
            const selectedOpt = select.options[select.selectedIndex];
            const varsCount = parseInt(selectedOpt.getAttribute('data-vars') || '0', 10);
            
            const container = document.getElementById('dynamic-variables-container');
            const inputsDiv = document.getElementById('dynamic-inputs');
            
            inputsDiv.innerHTML = '';
            
            if (varsCount > 0) {
                container.classList.remove('hidden');
                for (let i = 1; i <= varsCount; i++) {
                    inputsDiv.innerHTML += `
                        <div>
                            <label class="block text-[10px] font-semibold text-gray-400 uppercase tracking-wider mb-1">Değişken @{{${i}}}</label>
                            <input type="text" name="variables[]" required placeholder="@{{${i}}} içeriğini yazın" 
                                class="w-full bg-[#111827]/80 border border-gray-800 rounded-xl px-3 py-2.5 text-xs text-white focus:outline-none focus:border-emerald-500/50 transition">
                        </div>
                    `;
                }
            } else {
                container.classList.add('hidden');
            }
        }

        // Trigger / Start Campaign sending
        async function triggerCampaign(id) {
            if (!confirm('Kampanya gönderimini başlatmak istediğinize emin misiniz?')) {
                return;
            }
            
            // Parametreleri al
            let params = [];
            const varsDiv = document.getElementById('dynamic-inputs');
            if (varsDiv) {
                const inputs = varsDiv.querySelectorAll('input[name="variables[]"]');
                inputs.forEach(inp => params.push(inp.value));
            }

            try {
                await makeRequest(`/campaigns/${id}/trigger`, 'POST', { parameters: params });
                alert('Kampanya kuyruğa eklendi.');
                fetchCampaigns();
            } catch(err) {
                alert('Hata: ' + err.message);
            }
        }

        // New Campaign form submit
        document.getElementById('form-create-campaign').addEventListener('submit', async (e) => {
            e.preventDefault();
            const name = document.getElementById('campaign-name').value;
            const listId = document.getElementById('campaign-list-select').value;
            const templateId = document.getElementById('campaign-template-select').value;
            const throttle = document.getElementById('campaign-throttle').value;
            const schedule = document.getElementById('campaign-schedule').value;

            try {
                await makeRequest('/campaigns', 'POST', {
                    name,
                    template_id: templateId,
                    list_id: listId,
                    throttle_per_minute: throttle,
                    scheduled_at: schedule ? schedule : null
                });

                closeModal('modal-campaign');
                // Reset form
                document.getElementById('campaign-name').value = '';
                document.getElementById('campaign-schedule').value = '';
                document.getElementById('dynamic-inputs').innerHTML = '';
                document.getElementById('dynamic-variables-container').classList.add('hidden');
                
                fetchCampaigns();
            } catch (err) {
                alert('Hata: ' + err.message);
            }
        });

        // Show detailed failed messages
        async function showCampaignErrors(campaignId) {
            try {
                const res = await makeRequest(`/campaigns/${campaignId}/errors`);
                const container = document.getElementById('error-logs-container');
                container.innerHTML = '';

                const errors = res.data || [];
                if (errors.length === 0) {
                    container.innerHTML = '<p class="text-gray-400 text-center py-4">Bu kampanya kapsamında hata bulunmuyor.</p>';
                } else {
                    errors.forEach(err => {
                        container.innerHTML += `
                            <div class="py-3">
                                <div class="flex justify-between items-center">
                                    <span class="font-bold text-white font-mono">${err.contact?.phone_number}</span>
                                    <span class="text-xs text-gray-400">${new Date(err.updated_at).toLocaleString('tr-TR')}</span>
                                </div>
                                <p class="text-xs text-red-400 mt-1">${err.error_message || 'Bilinmeyen Meta API hatası.'}</p>
                            </div>
                        `;
                    });
                }
                openModal('modal-errors');
            } catch (err) {
                alert('Hata raporu çekilemedi: ' + err.message);
            }
        }

        // Modal Helpers
        function openModal(id) {
            document.getElementById(id).classList.remove('hidden');
            if (id === 'modal-campaign') {
                renderTemplateFields(); // select vars reset
            }
        }
        function closeModal(id) {
            document.getElementById(id).classList.add('hidden');
        }

        // Fetch current settings
        async function fetchSettings() {
            try {
                const settings = await makeRequest('/settings/whatsapp');
                document.getElementById('setting-api-url').value = settings.whatsapp_api_url || '';
                document.getElementById('setting-api-version').value = settings.whatsapp_api_version || '';
                document.getElementById('setting-phone-id').value = settings.whatsapp_phone_number_id || '';
                document.getElementById('setting-waba-id').value = settings.whatsapp_business_account_id || '';
                document.getElementById('setting-token').value = settings.whatsapp_token || '';
                document.getElementById('setting-app-secret').value = settings.whatsapp_app_secret || '';
                document.getElementById('setting-verify-token').value = settings.whatsapp_verify_token || '';
            } catch (err) {
                console.error('Ayarlar yüklenemedi:', err);
            }
        }

        // Save settings handler
        document.getElementById('form-settings-whatsapp').addEventListener('submit', async (e) => {
            e.preventDefault();
            const successDiv = document.getElementById('settings-success');
            const errorDiv = document.getElementById('settings-error');
            
            successDiv.classList.add('hidden');
            errorDiv.classList.add('hidden');

            const payload = {
                whatsapp_api_url: document.getElementById('setting-api-url').value,
                whatsapp_api_version: document.getElementById('setting-api-version').value,
                whatsapp_phone_number_id: document.getElementById('setting-phone-id').value,
                whatsapp_business_account_id: document.getElementById('setting-waba-id').value,
                whatsapp_token: document.getElementById('setting-token').value,
                whatsapp_app_secret: document.getElementById('setting-app-secret').value,
                whatsapp_verify_token: document.getElementById('setting-verify-token').value,
            };

            try {
                const res = await makeRequest('/settings/whatsapp', 'POST', payload);
                successDiv.classList.remove('hidden');
                setTimeout(() => successDiv.classList.add('hidden'), 5000);
            } catch (err) {
                errorDiv.textContent = err.message || 'Ayarlar güncellenirken bir hata oluştu.';
                errorDiv.classList.remove('hidden');
            }
        });
    </script>
</body>
</html>
