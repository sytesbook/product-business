# Infrastructure - Terraform

## Overview

Infrastructure as Code (IaC) using **Terraform** to manage cloud resources, networking, and deployment configurations for the Business Sytesbook application.

## Directory Structure

```
infrastructure/
├── environments/       # Environment-specific configurations
│   ├── dev/
│   ├── staging/
│   └── production/
├── modules/           # Reusable Terraform modules
│   ├── networking/
│   ├── compute/
│   ├── database/
│   └── storage/
├── shared/            # Shared resources across environments
├── scripts/           # Helper scripts for automation
└── README.md
```

## Technology Stack

- **Terraform**: 1.6+
- **Cloud Provider**: AWS
- **State Backend**: S3
- **Secrets**: GitHub environments

## Terraform Conventions

### Version Constraints

Always specify version constraints in `terraform` blocks:

```hcl
terraform {
  required_version = ">= 1.6.0"

  required_providers {
    aws = {
      source  = "hashicorp/aws"
      version = "~> 5.0"
    }
  }
}
```

### File Organization

Each environment and module should follow this structure:

```
environment/
├── main.tf           # Main resource definitions
├── variables.tf      # Input variables
├── outputs.tf        # Output values
├── providers.tf      # Provider configurations
├── backend.tf        # Backend configuration
├── versions.tf       # Version constraints
└── terraform.tfvars  # Variable values (not committed for sensitive data)
```

### Naming Conventions

- **Resources**: Use descriptive names with environment prefix
  ```hcl
  resource "aws_instance" "web_server_prod" { }
  ```

- **Variables**: Use snake_case
  ```hcl
  variable "instance_type" { }
  ```

- **Outputs**: Use snake_case with descriptive names
  ```hcl
  output "web_server_public_ip" { }
  ```

- **Modules**: Use kebab-case for directories
  ```
  modules/vpc-networking/
  ```

## State Management

### Remote State Backend

Configure remote state in `backend.tf`:

```hcl
terraform {
  backend "s3" {
    bucket         = "product-business-terraform-state"
    key            = "environments/production/terraform.tfstate"
    region         = "eu-central-1"
    encrypt        = true
    dynamodb_table = "terraform-state-lock"
  }
}
```

### State Backend Information

- **Bucket/Container**: `product-business-terraform-state`
- **Lock Table**: `terraform-state-lock` (DynamoDB for AWS)
- **Encryption**: Enabled (AES-256)
- **Versioning**: Enabled for disaster recovery

### State Best Practices

1. Never commit state files to version control
2. Use state locking to prevent concurrent modifications
3. Enable state versioning for rollback capability
4. Use separate state files per environment
5. Regularly backup state files

## Terraform Workflow

### Initial Setup

```bash
# Navigate to environment directory
cd infrastructure/environments/dev

# Initialize Terraform
terraform init

# Validate configuration
terraform validate

# Check formatting
terraform fmt -check
```

### Planning Changes

```bash
# Generate execution plan
terraform plan

# Save plan to file for review
terraform plan -out=tfplan

# Review plan in detail
terraform show tfplan
```

### Applying Changes

```bash
# Apply saved plan
terraform apply tfplan

# Or apply with auto-approve (use with caution)
terraform apply -auto-approve

# Apply specific resource
terraform apply -target=aws_instance.web_server
```

### Destroying Resources

```bash
# Preview destruction
terraform plan -destroy

# Destroy all resources (use with extreme caution)
terraform destroy

# Destroy specific resource
terraform destroy -target=aws_instance.web_server
```

### Common Operations

```bash
# Format code
terraform fmt -recursive

# Show current state
terraform show

# List resources in state
terraform state list

# Show specific resource
terraform state show aws_instance.web_server

# Refresh state
terraform refresh

# Import existing resource
terraform import aws_instance.web_server i-1234567890abcdef0

# Taint resource for recreation
terraform taint aws_instance.web_server

# Untaint resource
terraform untaint aws_instance.web_server
```

## Module Development

### Creating a Module

```bash
cd modules
mkdir my-module
cd my-module

# Create module files
touch main.tf variables.tf outputs.tf README.md
```

### Module Structure

```hcl
# modules/my-module/main.tf
resource "aws_instance" "this" {
  ami           = var.ami_id
  instance_type = var.instance_type

  tags = merge(
    var.tags,
    {
      Name = var.name
    }
  )
}

# modules/my-module/variables.tf
variable "ami_id" {
  description = "AMI ID for the instance"
  type        = string
}

variable "instance_type" {
  description = "Instance type"
  type        = string
  default     = "t3.micro"
}

variable "tags" {
  description = "Tags to apply to resources"
  type        = map(string)
  default     = {}
}

# modules/my-module/outputs.tf
output "instance_id" {
  description = "ID of the created instance"
  value       = aws_instance.this.id
}
```

### Using Modules

```hcl
module "web_server" {
  source = "../../modules/my-module"

  ami_id        = "ami-12345678"
  instance_type = "t3.small"

  tags = {
    Environment = "production"
    Project     = "product-business"
  }
}

output "web_server_id" {
  value = module.web_server.instance_id
}
```

## Environment Management

### Development Environment

```bash
cd infrastructure/environments/dev
terraform init
terraform plan
terraform apply
```

### Staging Environment

```bash
cd infrastructure/environments/staging
terraform init
terraform plan
terraform apply
```

### Production Environment

```bash
cd infrastructure/environments/production

# Extra caution for production
terraform plan -out=prod.tfplan

# Review plan thoroughly
terraform show prod.tfplan

# Get approval from team

# Apply changes
terraform apply prod.tfplan
```

## Secrets Management

### Using Variables for Secrets

Never commit secrets to version control. Use one of these approaches:

1. **Environment Variables**
   ```bash
   export TF_VAR_database_password="secretpassword"
   terraform apply
   ```

2. **Variable Files** (add to .gitignore)
   ```bash
   terraform apply -var-file="secrets.tfvars"
   ```

3. **Secrets Manager**
   ```hcl
   data "aws_secretsmanager_secret_version" "db_password" {
     secret_id = "prod/db/password"
   }

   resource "aws_db_instance" "main" {
     password = data.aws_secretsmanager_secret_version.db_password.secret_string
   }
   ```

## Code Quality

### Formatting

```bash
# Format all Terraform files
terraform fmt -recursive

# Check formatting without changes
terraform fmt -check -recursive
```

### Validation

```bash
# Validate configuration
terraform validate

# Validate with variable files
terraform validate -var-file="terraform.tfvars"
```

### Linting

Use `tflint` for advanced linting:

```bash
# Install tflint
brew install tflint  # macOS
# or download from https://github.com/terraform-linters/tflint

# Run linter
tflint

# Run with specific ruleset
tflint --config .tflint.hcl
```

### Security Scanning

Use `tfsec` for security scanning:

```bash
# Install tfsec
brew install tfsec  # macOS

# Run security scan
tfsec .

# Run with specific checks
tfsec --minimum-severity HIGH .
```

## Best Practices

### Code Organization

1. **Use modules** for reusable infrastructure patterns
2. **One environment per directory** with separate state files
3. **DRY principle** - don't repeat yourself, use modules
4. **Naming consistency** - follow naming conventions across all resources
5. **Documentation** - document modules and complex configurations

### Resource Management

1. **Tags**: Always tag resources with environment, project, owner
2. **Lifecycle**: Use `lifecycle` blocks to prevent accidental destruction
3. **Dependencies**: Use `depends_on` only when necessary
4. **Provisioners**: Avoid provisioners, use cloud-init or configuration management
5. **Data sources**: Use data sources instead of hardcoding values

### Security

1. **Secrets**: Never commit secrets, use secrets management
2. **IAM**: Follow principle of least privilege
3. **Encryption**: Enable encryption for data at rest and in transit
4. **Network**: Use private subnets, security groups, and NACLs
5. **Compliance**: Regular security audits with tfsec

### Performance

1. **State**: Keep state files small, split by environment
2. **Parallelism**: Use `-parallelism` flag for large deployments
3. **Caching**: Use `.terraform` cache, don't delete unnecessarily
4. **Planning**: Always review plans before applying

## Troubleshooting

### Common Issues

**State Lock Error**
```bash
# Force unlock (use with caution)
terraform force-unlock <LOCK_ID>
```

**Resource Already Exists**
```bash
# Import existing resource
terraform import aws_instance.web_server i-1234567890abcdef0
```

**State Drift**
```bash
# Refresh state to match real resources
terraform refresh

# Or import the changes
terraform plan -refresh-only
terraform apply -refresh-only
```

**Provider Plugin Issues**
```bash
# Clear provider cache
rm -rf .terraform
terraform init -upgrade
```

## CI/CD Integration

### Automated Planning

```bash
# In CI pipeline
terraform init -backend-config="token=$TF_API_TOKEN"
terraform plan -no-color -out=tfplan
terraform show -no-color tfplan > plan.txt
```

### Automated Application

```bash
# With approval gate
terraform apply -auto-approve tfplan
```

### Drift Detection

```bash
# Regularly check for drift
terraform plan -detailed-exitcode
# Exit code 0: no changes
# Exit code 2: changes detected
```

## Disaster Recovery

### State Backup

```bash
# Download current state
terraform state pull > backup.tfstate

# Upload state (extreme caution)
terraform state push backup.tfstate
```

### Resource Recovery

```bash
# Recreate resources from state
terraform apply -refresh-only
terraform apply
```

## Resources

- [Terraform Documentation](https://developer.hashicorp.com/terraform/docs)
- [Terraform Registry](https://registry.terraform.io/)
- [AWS Provider Documentation](https://registry.terraform.io/providers/hashicorp/aws/latest/docs)
- [Terraform Best Practices](https://www.terraform-best-practices.com/)
- [tflint](https://github.com/terraform-linters/tflint)
- [tfsec](https://github.com/aquasecurity/tfsec)

## Support

For infrastructure questions:
1. Check this documentation
2. Review existing modules and environments
3. Consult Terraform documentation
4. Contact DevOps team
