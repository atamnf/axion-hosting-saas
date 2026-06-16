<p align="center">
  <a href="README.md">English</a> |
  <a href="README_RU.md">Русский</a>
</p>

# Axion Hosting Platform

A SaaS platform for selling and managing game servers and web hosting.

The project is a full-featured system where, after purchasing a plan, a user receives real computing resources on a virtual dedicated server (VDS) and access to a server management panel to run their own servers, applications, and services.

🌐 Website: https://axion-hosting.ru  
💬 Discord: https://discord.gg/pYeqyfKJmZ

---

##  Project Status

This project was developed as an educational and practical startup and a production-like system.

At the moment, the platform is not in active commercial use and is primarily considered a demonstration of architectural and engineering solutions.

---

##  Key Features

- User registration and authentication  
- OAuth authentication via Discord and Telegram  
- User dashboard (personal account)  
- Purchase of hosting plans  
- Automatic creation of accounts in the server management panel (Pterodactyl)  
- Allocation of real VDS resources after purchase  
- Delivery of panel access credentials to the user  
- Server and service management through the control panel  
- Admin panel for system management  
- Internal REST API for service orchestration  
- Webhooks for payment processing  

---

##  Technology Stack

**Backend**
- PHP  
- REST API  

**Frontend**
- HTML / CSS  

**Database**
- MySQL  

**Integrations**
- Payment system integration  
- Discord OAuth  
- Telegram OAuth  
- Pterodactyl Panel API  

**Infrastructure**
- Linux  
- Nginx / Apache  
- Virtual Dedicated Servers (VDS)

## Repository Contents

- Sanitized PHP backend source code
- Authentication flow via Discord and Telegram
- Payment webhook handling
- Pterodactyl provisioning logic
- API documentation
- Database structure documentation
- Security notes
- Architecture diagrams and screenshots

##  System Architecture

The platform consists of the following core components:

- Web Application (Frontend + Backend)  
- Authentication Service (Discord / Telegram OAuth)  
- Billing & Order Service  
- Provisioning Service (account and resource creation)  
- Integration with Pterodactyl Panel  
- Admin Panel  
- Internal API  
- Database  

### Provisioning Flow (Simplified)

1. A user registers and logs into the system  
2. The user selects a plan and places an order  
3. After payment confirmation:
   - an account is automatically created in the Pterodactyl panel  
   - computing resources are allocated on a VDS  
   - access credentials to the panel are generated  
4. The user receives access to the server management panel  
5. Server lifecycle management is performed directly through Pterodactyl  

Thus, the platform provides users with real dedicated server resources for running their own servers, applications, and services.

---

## Security & Source Code

This repository contains a sanitized public version of the Axion Hosting SaaS codebase.

All production secrets, API keys, tokens, webhook URLs, database credentials, user-uploaded files, and deployment-specific configuration have been removed.

The project is published as a portfolio case study to demonstrate the architecture, backend logic, integrations, and provisioning workflow of a hosting SaaS platform.

Production deployment requires creating a local `.env` file based on `.env.example`.


---

##  Team

The project was developed by a small team of two:

- Backend / System Architecture — atamnf
- Full-Stack Developer — improving1337  

---

##  What This Project Demonstrates

- Designing SaaS platforms  
- Automated provisioning of services on VDS  
- Integration with external control panels (Pterodactyl)  
- OAuth authentication (Discord, Telegram)  
- Payment system integration  
- REST API design  
- Provisioning and orchestration systems  
- Infrastructure and system administration  

---

##  Links

- Website: https://axion-hosting.ru  
- Discord: https://discord.gg/pYeqyfKJmZ 

##  Screenshots

![Login](screenshots/login.png)  
![Dashboard](screenshots/dashboard.png)  
![Panel](screenshots/panel.png)

##  Architecture

- [System Overview](architecture/system_overview.md)
- [System Components](architecture/components.md)
