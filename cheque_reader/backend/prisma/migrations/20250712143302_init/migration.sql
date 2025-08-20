-- CreateTable
CREATE TABLE "ChequeJob" (
    "id" INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
    "jobId" TEXT NOT NULL,
    "amount" TEXT NOT NULL,
    "remarks" TEXT,
    "frontImage" TEXT NOT NULL,
    "backImage" TEXT NOT NULL,
    "createdAt" DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    "updatedAt" DATETIME NOT NULL
);

-- CreateIndex
CREATE UNIQUE INDEX "ChequeJob_jobId_key" ON "ChequeJob"("jobId");
