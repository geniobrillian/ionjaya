# Terraform untuk deploy UD Ion Jaya Motor ke Azure App Service (B1)
# Proyek: Laravel 12 + PHP 8.2 + MySQL 8.0 (sistem persediaan FSN + ROP)
#
# Cara pakai: baca README.md di folder ini.
# JANGAN jalankan `terraform apply` sebelum membaca estimasi biaya di README.

terraform {
  required_version = ">= 1.5.0"

  required_providers {
    azurerm = {
      source  = "hashicorp/azurerm"
      version = "~> 4.0"
    }
    random = {
      source  = "hashicorp/random"
      version = "~> 3.6"
    }
  }
}
