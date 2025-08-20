import express from 'express';
import cors from 'cors';
import { PrismaClient } from '@prisma/client';
import { ChequeJobController } from './controllers/ChequeJobController';
import { ChequeJobService } from './services/ChequeJobService';
import { NotificationService } from './services/NotificationService';
import { FraudDetectionService } from './services/FraudDetectionService';
import { ValidationService } from './services/ValidationService';
import { ChequeJobRepository } from './repositories/ChequeJobRepository';
import { upload } from './middlewares/upload';
import { errorHandler } from './middlewares/errorHandler';

const app = express();
const prisma = new PrismaClient();


const chequeJobRepository = new ChequeJobRepository(prisma);
const notificationService = new NotificationService();
const fraudDetectionService = new FraudDetectionService();
const validationService = new ValidationService();
const chequeJobService = new ChequeJobService(
  prisma,
  notificationService,
  fraudDetectionService,
  validationService
);
const chequeJobController = new ChequeJobController(chequeJobService);

const PORT = process.env.PORT || 5000;

app.use(cors());
app.use(express.json());


app.post(
  '/api/deposit-check',
  upload.fields([
    { name: 'frontImage', maxCount: 1 },
    { name: 'backImage', maxCount: 1 },
  ]),
  chequeJobController.depositCheck
);

app.get('/api/jobs/:jobId', chequeJobController.getJobsById);
app.post('/api/jobs', chequeJobController.findJobs);


app.post('/api/jobExist', chequeJobController.existJob);


// Error handler middleware
app.use(errorHandler);

app.listen(PORT, () => {
  console.log(`Cheque Reader backend running on port ${PORT}`);
});
