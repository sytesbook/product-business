#!/bin/bash
# Create EventBridge event bus with SQS queue targets for local development
# This script runs automatically when LocalStack is ready
# Mounted at: /etc/localstack/init/ready.d/01-create-event-bus.sh

set -e

echo "Setting up event infrastructure for local development..."

REGION="${AWS_DEFAULT_REGION:-eu-central-1}"
ACCOUNT_ID="000000000000"
ENDPOINT="http://localhost:4566"

# ---------------------------------------------------------------------------
# 1. EventBridge event bus
# ---------------------------------------------------------------------------
echo "Creating EventBridge event bus 'backend-events'..."
aws events create-event-bus \
    --name backend-events \
    --endpoint-url "${ENDPOINT}" \
    --region "${REGION}"

# ---------------------------------------------------------------------------
# 2. SQS queues (one per consumer service)
# ---------------------------------------------------------------------------
echo "Creating SQS queues..."

aws sqs create-queue \
    --queue-name content-service-events \
    --endpoint-url "${ENDPOINT}" \
    --region "${REGION}"

aws sqs create-queue \
    --queue-name wp-customer-sites-events \
    --endpoint-url "${ENDPOINT}" \
    --region "${REGION}"

aws sqs create-queue \
    --queue-name router-service-events \
    --endpoint-url "${ENDPOINT}" \
    --region "${REGION}"

# ---------------------------------------------------------------------------
# 3. EventBridge rule — catch all events published to the bus
# ---------------------------------------------------------------------------
echo "Creating EventBridge catch-all rule on 'backend-events'..."
aws events put-rule \
    --name backend-events-fanout \
    --event-bus-name backend-events \
    --event-pattern '{"source":[{"prefix":""}]}' \
    --state ENABLED \
    --endpoint-url "${ENDPOINT}" \
    --region "${REGION}"

# ---------------------------------------------------------------------------
# 4. Attach all three SQS queues as targets (fan-out)
# ---------------------------------------------------------------------------
echo "Attaching SQS queues as rule targets..."

CONTENT_SERVICE_QUEUE_ARN="arn:aws:sqs:${REGION}:${ACCOUNT_ID}:content-service-events"
WP_CUSTOMER_SITES_QUEUE_ARN="arn:aws:sqs:${REGION}:${ACCOUNT_ID}:wp-customer-sites-events"
THIRD_SERVICE_QUEUE_ARN="arn:aws:sqs:${REGION}:${ACCOUNT_ID}:router-service-events"

aws events put-targets \
    --rule backend-events-fanout \
    --event-bus-name backend-events \
    --targets \
        "Id=content-service,Arn=${CONTENT_SERVICE_QUEUE_ARN}" \
        "Id=wp-customer-sites,Arn=${WP_CUSTOMER_SITES_QUEUE_ARN}" \
        "Id=third-service,Arn=${THIRD_SERVICE_QUEUE_ARN}" \
    --endpoint-url "${ENDPOINT}" \
    --region "${REGION}"

echo ""
echo "Event infrastructure created successfully!"
echo ""
echo "  Event bus:  arn:aws:events:${REGION}:${ACCOUNT_ID}:event-bus/backend-events"
echo "  Queues:"
echo "    content-service-events     arn:aws:sqs:${REGION}:${ACCOUNT_ID}:content-service-events"
echo "    wp-customer-sites-events   arn:aws:sqs:${REGION}:${ACCOUNT_ID}:wp-customer-sites-events"
echo "    router-service-events       arn:aws:sqs:${REGION}:${ACCOUNT_ID}:router-service-events"
echo ""
echo "  Queue URLs (from within Docker network):"
echo "    http://localstack:4566/000000000000/content-service-events"
echo "    http://localstack:4566/000000000000/wp-customer-sites-events"
echo "    http://localstack:4566/000000000000/router-service-events"
