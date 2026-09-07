# Frontend Dockerfile (Development & Testing Build)
FROM node:20-alpine AS builder

# Set working directory
WORKDIR /app

# Copy package files
COPY package*.json ./

# Install all dependencies (including devDependencies required for Vite & Tailwind build)
RUN npm ci

# Copy application files
COPY . .

# Build argument for API endpoint
ARG VITE_API_URL
ENV VITE_API_URL=${VITE_API_URL:-http://localhost:8000/api}

# Build application
RUN npm run build

# Production runtime stage with Nginx
FROM nginx:alpine

# Copy built static assets from builder stage
COPY --from=builder /app/dist /usr/share/nginx/html

# Copy Nginx configuration from frontend context
COPY nginx.conf /etc/nginx/conf.d/default.conf

# Expose port
EXPOSE 80

# Start nginx
CMD ["nginx", "-g", "daemon off;"]
