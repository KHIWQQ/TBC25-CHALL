# Use Node.js 18 for Prisma compatibility
FROM node:18-alpine

# Set working directory
WORKDIR /app

# Install dependencies
COPY package.json package-lock.json ./
RUN npm install

# Install Prisma CLI globally (for migrations in container)
RUN npm install -g prisma

# Copy the rest of the code
COPY . .

# Set environment variable for SQLite
ENV DATABASE_URL="file:./dev.db"

# Generate Prisma client
RUN npx prisma generate

# Run migrations (for dev, you may want to use migrate deploy for prod)
RUN npx prisma migrate deploy || npx prisma migrate dev --name init --skip-seed
# CMD npx prisma migrate dev --name init --skip-seed && node dist/index.js

# Build TypeScript
RUN npx tsc

# Expose port
EXPOSE 5000

# Start the server
# CMD npx prisma migrate deploy --name init --skip-seed && node dist/index.js 
CMD npx prisma migrate deploy && node dist/index.js