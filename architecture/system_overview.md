# System Overview

Axion Hosting is a SaaS platform for automated provisioning of hosting services on virtual dedicated servers (VDS).

The system is designed to automate the full lifecycle of a service:
order → payment → provisioning → access delivery → management.

## Main Components

- Web Application (Frontend + Backend)
- Authentication Service (Discord / Telegram OAuth)
- Billing & Order Service
- Provisioning Service
- Integration with Pterodactyl Panel
- Admin Panel
- Internal REST API
- Database

## High-Level Flow

1. User registers and authenticates via the web platform  
2. User selects a hosting plan and places an order  
3. Payment is processed by an external payment system  
4. After successful payment:
   - a new account is created in the Pterodactyl panel  
   - computing resources are allocated on a VDS  
   - access credentials are generated and delivered to the user  
5. User manages servers and services through the control panel  

## Design Goals

- Full automation of service provisioning  
- Isolation of user resources  
- Secure authentication and authorization  
- Minimal manual intervention by administrators  
- Extensibility for new service types  

This document provides a high-level overview of the system architecture and main design principles.
