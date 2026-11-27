# ============================================================
# UD Ion Jaya Motor — Azure App Service (B1) + MySQL Flexible
# ============================================================
# Yang dibuat di sini:
#   1. Resource Group
#   2. App Service Plan B1 (Linux) — tempat aplikasi Laravel jalan
#   3. Linux Web App PHP 8.2 — aplikasinya (nama unik global)
#   4. MySQL Flexible Server B1ms + database ionjaya_db
#   5. Firewall: izinkan App Service (semua layanan Azure) + IP laptop (opsional)
#   6. require_secure_transport = OFF (Laravel PDO default; tanpa SSL client)
#
# Yang TIDAK dibuat di sini (sengaja, karena tidak dibutuhkan proyek ini):
#   - Redis / Cache eksternal: session + cache pakai database (SESSION_DRIVER
#     dan CACHE_STORE = database, sesuai .env).
#   - Queue worker: QUEUE_CONNECTION = sync, karena aplikasi tidak
#     mengantre job (tabel jobs kosong; di localhost juga tanpa worker).
#   - Application Insights: tidak wajib untuk semhas; log cukup dari
#     file log App Service + storage/logs/laravel.log.
# ============================================================

# --- Nama unik global (Web App & MySQL harus unik se-Azure) ---
resource "random_string" "suffix" {
  length  = 4
  special = false
  upper   = false
}

# --- Password admin MySQL (dibuat acak, tersimpan di Terraform state) ---
resource "random_password" "mysql_admin" {
  length  = 20
  special = false # tanpa karakter khusus: aman dipakai di mysql CLI & app settings
}

locals {
  common_tags = {
    Project     = var.project
    Environment = var.environment
    ManagedBy   = "terraform"
  }
  webapp_name = "${var.project}-${random_string.suffix.result}"
  mysql_name  = "${var.project}-mysql-${random_string.suffix.result}"
}

# --- 1. Resource Group ---
resource "azurerm_resource_group" "main" {
  name     = "${var.project}-${var.environment}-rg"
  location = var.location
  tags     = local.common_tags
}

# --- 2. App Service Plan B1 (Linux) ---
resource "azurerm_service_plan" "main" {
  name                = "${var.project}-${var.environment}-plan"
  location            = azurerm_resource_group.main.location
  resource_group_name = azurerm_resource_group.main.name
  os_type             = "Linux"
  sku_name            = "B1"
  tags                = local.common_tags
}

# --- 3. Linux Web App (PHP 8.2) ---
resource "azurerm_linux_web_app" "main" {
  name                = local.webapp_name
  location            = azurerm_resource_group.main.location
  resource_group_name = azurerm_resource_group.main.name
  service_plan_id     = azurerm_service_plan.main.id
  https_only          = true
  tags                = local.common_tags

  site_config {
    always_on           = true # B1 mendukung Always On: aplikasi tidak "tidur"
    ftps_state          = "Disabled"
    http2_enabled       = true
    minimum_tls_version = "1.2" # di azurerm v4 namanya minimum_tls_version

    application_stack {
      php_version = "8.2" # Laravel 12 butuh PHP ^8.2 (composer.json)
    }
  }

  # Variabel environment Laravel (.env versi server).
  # Nilai DB_* diambil langsung dari resource MySQL di bawah — tidak
  # perlu diketik manual, dan password tidak tertulis di file ini.
  app_settings = {
    APP_NAME                       = var.app_name
    APP_ENV                        = var.environment
    APP_DEBUG                      = "false" # WAJIB false di server (sembunyikan error detail)
    APP_URL                        = "https://${local.webapp_name}.azurewebsites.net"
    APP_KEY                        = var.app_key
    LOG_CHANNEL                    = "stack"
    LOG_LEVEL                      = "error"
    DB_CONNECTION                  = "mysql"
    DB_HOST                        = azurerm_mysql_flexible_server.main.fqdn
    DB_PORT                        = "3306"
    DB_DATABASE                    = azurerm_mysql_flexible_database.main.name
    DB_USERNAME                    = var.mysql_admin_login
    DB_PASSWORD                    = random_password.mysql_admin.result
    SESSION_DRIVER                 = "database"
    SESSION_LIFETIME               = "120"
    CACHE_STORE                    = "database"
    QUEUE_CONNECTION               = "sync" # tanpa worker; lihat catatan di atas
    FILESYSTEM_DISK                = "local"
    MAIL_MAILER                    = "log"
    SCM_DO_BUILD_DURING_DEPLOYMENT = "true" # Oryx: composer install saat deploy
  }

  logs {
    http_logs {
      file_system {
        retention_in_days = 7
        retention_in_mb   = 35
      }
    }
  }
}

# --- 4. MySQL Flexible Server (Burstable B1ms — tier termurah) ---
resource "azurerm_mysql_flexible_server" "main" {
  name                   = local.mysql_name
  location               = azurerm_resource_group.main.location
  resource_group_name    = azurerm_resource_group.main.name
  administrator_login    = var.mysql_admin_login
  administrator_password = random_password.mysql_admin.result
  sku_name               = "B_Standard_B1ms"
  version                = "8.0.21"
  zone                   = "1"

  storage {
    size_gb           = 20
    auto_grow_enabled = true
  }

  backup_retention_days        = 7
  geo_redundant_backup_enabled = false # hemat biaya; backup lokal saja

  tags = local.common_tags
}

resource "azurerm_mysql_flexible_database" "main" {
  name                = var.mysql_database_name
  resource_group_name = azurerm_resource_group.main.name
  server_name         = azurerm_mysql_flexible_server.main.name
  charset             = "utf8mb4"
  collation           = "utf8mb4_unicode_ci"
}

# --- 5. Firewall MySQL ---
# Izinkan semua layanan Azure (termasuk App Service di atas) konek ke MySQL.
# Ini pola standar untuk demo; akses tetap butuh username + password.
resource "azurerm_mysql_flexible_server_firewall_rule" "allow_azure" {
  name                = "AllowAllAzureServices"
  resource_group_name = azurerm_resource_group.main.name
  server_name         = azurerm_mysql_flexible_server.main.name
  start_ip_address    = "0.0.0.0"
  end_ip_address      = "0.0.0.0"
}

# IP laptop (opsional) — hanya dibuat kalau admin_client_ip diisi.
resource "azurerm_mysql_flexible_server_firewall_rule" "allow_admin_ip" {
  count               = var.admin_client_ip != "" ? 1 : 0
  name                = "AllowAdminLaptop"
  resource_group_name = azurerm_resource_group.main.name
  server_name         = azurerm_mysql_flexible_server.main.name
  start_ip_address    = var.admin_client_ip
  end_ip_address      = var.admin_client_ip
}

# --- 6. Matikan wajib SSL di MySQL ---
# Laravel PDO default konek tanpa sertifikat SSL. Kalau ini ON, koneksi
# dari App Service gagal kecuali DSN dikonfigurasi SSL. Untuk semhas,
# OFF sudah cukup (trafik tetap di dalam jaringan Azure).
resource "azurerm_mysql_flexible_server_configuration" "require_secure_transport" {
  name                = "require_secure_transport"
  resource_group_name = azurerm_resource_group.main.name
  server_name         = azurerm_mysql_flexible_server.main.name
  value               = "OFF"
}
