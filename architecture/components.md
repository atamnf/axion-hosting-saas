# System Components

This document describes the main components of the Axion Hosting platform and their responsibilities.

---

## 1. Web Application

**Responsibility:**
- Provides the main user interface of the platform  
- Handles user registration and authentication  
- Displays user dashboard and available services  
- Sends user actions to backend services via REST API  

**Main functions:**
- User signup / login  
- Plan selection and order creation  
- Display of service status  
- Delivery of access credentials to the user  

**Technologies:**
- PHP  
- HTML / CSS  

---

## 2. Authentication Service

**Responsibility:**
- Handles user authentication and authorization  
- Integrates with external OAuth providers  

**Main functions:**
- OAuth login via Discord  
- OAuth login via Telegram  
- Session management  
- User identity mapping  

**Design notes:**
- OAuth is used to reduce password handling  
- External providers are trusted identity sources  

---

## 3. Billing & Order Service

**Responsibility:**
- Manages orders and payment lifecycle  

**Main functions:**
- Create orders for hosting plans  
- Track order status  
- Receive payment confirmation via webhooks  
- Trigger provisioning after successful payment  

**Design notes:**
- Billing is isolated from provisioning logic  
- Payment system is treated as an external trusted service  

---

## 4. Provisioning Service

**Responsibility:**
- Automates creation of hosting services after payment  

**Main functions:**
- Create user accounts in Pterodactyl panel  
- Allocate computing resources on VDS  
- Generate access credentials  
- Link provisioned resources to internal user account  

**Design notes:**
- Provisioning is fully automated  
- Manual administrator intervention is not required  
- Errors are logged and reported to admin panel  

---

## 5. Pterodactyl Integration

**Responsibility:**
- Integrates the platform with external server management panel  

**Main functions:**
- Create and manage user accounts via Pterodactyl API  
- Create and configure servers  
- Synchronize server status  

**Design notes:**
- Pterodactyl is treated as an external control plane  
- Communication is performed via REST API  
- API credentials are stored securely outside the repository  

---

## 6. Admin Panel

**Responsibility:**
- Provides administrative control over the platform  

**Main functions:**
- View users and orders  
- Monitor provisioning status  
- Manage hosting plans  
- Handle failed provisioning cases  

**Design notes:**
- Admin panel is accessible only to privileged users  
- Critical actions require authentication and authorization  

---

## 7. Internal REST API

**Responsibility:**
- Provides communication between system components  

**Main functions:**
- User management endpoints  
- Order and billing endpoints  
- Provisioning control endpoints  

**Design notes:**
- API is used by both Web Application and Admin Panel  
- Authentication is required for all internal endpoints  

---

## 8. Database

**Responsibility:**
- Stores all persistent system data  

**Main entities:**
- Users  
- Orders  
- Payments  
- Provisioned services  
- Access credentials (hashed / encrypted)  

**Design notes:**
- Relational database is used  
- Data consistency is enforced by transactions  
- Sensitive data is stored in encrypted form  

---

This document provides a detailed overview of the responsibilities and design of each core system component.
