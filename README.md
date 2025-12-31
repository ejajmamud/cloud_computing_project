# 🎓 UniKL SkillSwap - Cloud Infrastructure Project

**Course:** IBB43203 - Cloud Computing  
**University:** Universiti Kuala Lumpur (UniKL)  

---

## 📋 Project Overview
**UniKL SkillSwap** is a scalable, cloud-native Peer-to-Peer Learning Management System (LMS) deployed on **Amazon Web Services (AWS)**. 

Moving away from a traditional monolithic local setup, our team designed a distributed architecture leveraging **Infrastructure as Code (IaC)** via AWS CDK. The project demonstrates a production-grade deployment securing web traffic with SSL, managing database state via RDS, and distributing traffic via an Application Load Balancer.

## 🚀 Live Demo & Presentation
* **🌐 Live Website (Secure HTTPS):** [https://d37l2w631tel5p.cloudfront.net/](https://d37l2w631tel5p.cloudfront.net/)
* **📺 Video Presentation:** [Watch on YouTube](https://youtu.be/2te-KH5CRK8)
* **📂 Project Report:** [View PDF in Repo](Project%20Report_Cloud_Computing.pdf)

---

## 👥 Group Members
| Name | Student ID | Role |
| :--- | :--- | :--- |
| **Md Ejaj Mahmud** | 52222222123 | Cloud Architect & Lead Developer |
| **Muhammad Zulhafiq Bin Rohalizam** | 52222121141 | Infrastructure Engineer (CDK/S3) |
| **Muhammad Nur Amiruddin Bin Muhamad** | 52222122265 | System Administrator (EC2/SSM) |

---

## 🏗 AWS Cloud Architecture
We implemented a **3-Tier Architecture** to ensure high availability, security, and performance.

### **AWS Services Used:**
* **Compute:** Amazon EC2 (t3.micro, CentOS 7) running Nginx & PHP 8.2.
* **Database:** Amazon RDS (MySQL 8.0) in a private subnet.
* **Networking:**
    * **VPC:** Custom Virtual Private Cloud with Public/Private isolation.
    * **ALB:** Application Load Balancer for traffic distribution and health checks.
    * **CloudFront:** CDN for caching static assets and **SSL/HTTPS Termination**.
* **Storage:**
    * **Amazon S3:** Secure artifact storage for source code and database dumps.
    * **Amazon EBS:** Persistent block storage for the application server.
* **DevOps:** AWS CDK (Infrastructure as Code) & Systems Manager (SSM) for secure access.

---

## 🛠 Technical Highlights & Troubleshooting
During deployment, we solved several critical engineering challenges:

### 1. **504 Gateway Timeout**
* **Issue:** The Load Balancer could not connect to the EC2 instance.
* **Fix:** configured **Security Groups** to explicitly allow inbound traffic on Port 80 from the ALB.

### 2. **419 Authentication Error (CSRF)**
* **Issue:** Laravel Login failed because the Load Balancer stripped headers, causing token mismatches.
* **Fix:** Configured `TrustProxies` middleware to trust AWS Elastic Load Balancer IPs and adjusted `VerifyCsrfToken` exceptions.

### 3. **SSL/HTTPS Implementation**
* **Issue:** The AWS Load Balancer (Free Tier) does not provide a default SSL certificate.
* **Fix:** Implemented **SSL Offloading** using **AWS CloudFront**. The user connects via HTTPS to CloudFront, which communicates internally with the ALB via HTTP, ensuring a secure "Green Lock" experience.

---

## 📂 Repository Structure
* `lib/` - **Infrastructure as Code:** TypeScript definitions for the AWS CDK stack.
* `laravel-code/` - **Source Code:** The core PHP/Laravel application files.
* `Project Report_Cloud_Computing.pdf` - **Documentation:** Full step-by-step project report.
* `skillswap.sql` - **Database Schema:** (Note: Sensitive keys removed for security).
* `screenshots/` - **Evidence:** Proof of deployment and error resolution.

---

## ⚙️ Deployment Steps (How to Replicate)

### Prerequisites
* AWS Account & CLI configured
* Node.js & AWS CDK installed

### 1. Provision Infrastructure
```bash
npm install
cdk bootstrap
cdk deploy
