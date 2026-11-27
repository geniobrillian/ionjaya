# Variabel untuk provision Azure App Service (B1) + MySQL Flexible Server.
# Nilai rahasia (app_key) diisi lewat terraform.tfvars — JANGAN di-commit.

variable "project" {
  description = "Nama proyek, dipakai sebagai awalan nama resource Azure."
  type        = string
  default     = "ionjaya"
}

variable "location" {
  description = "Region Azure. Southeast Asia = Singapura, paling dekat dengan Jombang."
  type        = string
  default     = "southeastasia"
}

variable "environment" {
  description = "Nama environment, dipakai untuk tag dan APP_ENV."
  type        = string
  default     = "production"
}

variable "app_name" {
  description = "APP_NAME Laravel (nama aplikasi yang tampil di sistem)."
  type        = string
  default     = "Ion Jaya Motor"
}

variable "app_key" {
  description = <<-EOT
    APP_KEY Laravel (wajib diisi). Ambil dari .env di laptop:
    php artisan key:generate --show
    Boleh pakai APP_KEY yang sama dengan .env lokal — kuncinya hanya
    dipakai untuk enkripsi session/cookie, bukan password.
  EOT
  type        = string
  sensitive   = true

  validation {
    condition     = startswith(var.app_key, "base64:")
    error_message = "app_key harus diawali 'base64:' (hasil php artisan key:generate --show)."
  }
}

variable "mysql_admin_login" {
  description = "Username admin MySQL Flexible Server (bukan 'admin' / 'azure_superuser')."
  type        = string
  default     = "ionjayaadmin"
}

variable "mysql_database_name" {
  description = "Nama database MySQL. Samakan dengan DB_DATABASE lokal (ionjaya_db)."
  type        = string
  default     = "ionjaya_db"
}

variable "admin_client_ip" {
  description = <<-EOT
    IP publik laptopmu (opsional, kosongkan bila tidak perlu).
    Kalau diisi, IP ini diizinkan konek langsung ke MySQL (misalnya untuk
    cek isi database dari MySQL Workbench). Cek IP-mu di https://ifconfig.me
  EOT
  type        = string
  default     = ""
}
