#!/bin/bash
# Create SNS topics for local development
# This script runs automatically when LocalStack is ready
# Mounted at: /etc/localstack/init/ready.d/01-create-sns-topic.sh

set -e

echo "Creating SNS topics for local development..."

REGION="${AWS_DEFAULT_REGION:-us-east-1}"

aws sns create-topic \
    --name backend-events \
    --endpoint-url "http://localhost:4566" \
    --region "${REGION}" \
    --output text

echo "SNS topic 'backend-events' created successfully!"
echo "Local SNS topic ARN: arn:aws:sns:${REGION}:000000000000:backend-events"
