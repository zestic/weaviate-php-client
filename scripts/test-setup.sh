#!/bin/bash

# Test setup script for Weaviate PHP Client
set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

echo "🚀 Setting up Weaviate test environment..."

# Function to check if Docker is running
check_docker() {
    if ! docker info > /dev/null 2>&1; then
        echo "❌ Docker is not running. Please start Docker and try again."
        exit 1
    fi
}

# Function to start Weaviate
start_weaviate() {
    echo "📦 Starting Weaviate container..."
    cd "$PROJECT_DIR"
    docker compose up -d weaviate
    
    echo "⏳ Waiting for Weaviate to be ready..."
    timeout=60
    counter=0
    
    while [ $counter -lt $timeout ]; do
        if curl -f http://localhost:18080/v1/.well-known/ready > /dev/null 2>&1; then
            echo "✅ Weaviate is ready!"
            return 0
        fi
        sleep 2
        counter=$((counter + 2))
        echo "   Waiting... ($counter/${timeout}s)"
    done
    
    echo "❌ Weaviate failed to start within ${timeout} seconds"
    docker compose logs weaviate
    exit 1
}

# Function to stop Weaviate
stop_weaviate() {
    echo "🛑 Stopping Weaviate container..."
    cd "$PROJECT_DIR"
    docker compose down
    echo "✅ Weaviate stopped"
}

# Function to reset Weaviate data
reset_weaviate() {
    echo "🔄 Resetting Weaviate data..."
    cd "$PROJECT_DIR"
    docker compose down -v
    docker compose up -d weaviate
    
    echo "⏳ Waiting for Weaviate to be ready after reset..."
    timeout=60
    counter=0
    
    while [ $counter -lt $timeout ]; do
        if curl -f http://localhost:18080/v1/.well-known/ready > /dev/null 2>&1; then
            echo "✅ Weaviate is ready after reset!"
            return 0
        fi
        sleep 2
        counter=$((counter + 2))
        echo "   Waiting... ($counter/${timeout}s)"
    done
    
    echo "❌ Weaviate failed to start after reset within ${timeout} seconds"
    exit 1
}

# Function to run tests
run_tests() {
    echo "🧪 Running tests..."
    cd "$PROJECT_DIR"
    
    case "${1:-all}" in
        "unit")
            ./vendor/bin/phpunit tests/Unit
            ;;
        "integration")
            ./vendor/bin/phpunit tests/Integration
            ;;
        "all")
            ./vendor/bin/phpunit
            ;;
        *)
            echo "❌ Unknown test type: $1"
            echo "Usage: $0 test [unit|integration|all]"
            exit 1
            ;;
    esac
}

# Main script logic
case "${1:-help}" in
    "start")
        check_docker
        start_weaviate
        ;;
    "stop")
        stop_weaviate
        ;;
    "reset")
        check_docker
        reset_weaviate
        ;;
    "test")
        check_docker
        start_weaviate
        run_tests "$2"
        ;;
    "help"|*)
        echo "Weaviate PHP Client Test Setup"
        echo ""
        echo "Usage: $0 <command> [options]"
        echo ""
        echo "Commands:"
        echo "  start              Start Weaviate container"
        echo "  stop               Stop Weaviate container"
        echo "  reset              Reset Weaviate data (stop, remove volumes, start)"
        echo "  test [type]        Start Weaviate and run tests"
        echo "                     Types: unit, integration, all (default: all)"
        echo "  help               Show this help message"
        echo ""
        echo "Examples:"
        echo "  $0 start           # Start Weaviate"
        echo "  $0 test unit       # Start Weaviate and run unit tests"
        echo "  $0 test integration # Start Weaviate and run integration tests"
        echo "  $0 reset           # Reset Weaviate data"
        echo "  $0 stop            # Stop Weaviate"
        ;;
esac
