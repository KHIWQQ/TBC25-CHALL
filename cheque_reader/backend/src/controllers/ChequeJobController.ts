import { Request, Response } from 'express';
import { ChequeJobService } from '../services/ChequeJobService';

export class ChequeJobController {
  constructor(private chequeJobService: ChequeJobService) {}

  depositCheck = async (req: Request, res: Response) => {
    try {
      const { amount, remarks } = req.body;
      const frontImage = req.files && (req.files as any).frontImage?.[0];
      const backImage = req.files && (req.files as any).backImage?.[0];
      if (!amount || !frontImage || !backImage) {
        return res.status(400).json({ success: false, message: 'Missing required fields', timestamp: new Date().toISOString() });
      }
      const jobId = require('uuid').v4();
      const job = await this.chequeJobService.createChequeJob({
        jobId,
        amount,
        remarks: remarks || null,
        frontImage: frontImage.path,
        backImage: backImage.path,
      });
      return res.json({
        success: true,
        jobId,
        message: `we have processed your cheque with job ID ${jobId} and secret ${job.secret}`,
        secret: job.secret,
        timestamp: new Date().toISOString(),
      });
    } catch (err) {
      console.error('Error in depositCheck:', err);
      return res.status(500).json({ success: false, message: 'Internal server error', timestamp: new Date().toISOString() });
    }
  };

  getJobsById = async (req: Request, res: Response) => {
    try {
      const { jobId } = req.params;
      const { secret } = req.query;
      if (!secret || typeof secret !== 'string') {
        return res.status(403).json({ success: false, message: 'Secret is required', timestamp: new Date().toISOString() });
      }
      const job = await this.chequeJobService.getChequeJobById(jobId);
      if (!job) {
        return res.status(404).json({ success: false, message: 'Job not found', timestamp: new Date().toISOString() });
      }
      if (job.secret !== secret) {
        return res.status(403).json({ success: false, message: 'Invalid secret', timestamp: new Date().toISOString() });
      }
      return res.json({
        success: true,
        jobId,
        remarks: job.remarks,
        timestamp: new Date().toISOString(),
      });
    } catch (err) {
      console.error('Error in getRemarksById:', err);
      return res.status(500).json({ success: false, message: 'Internal server error', timestamp: new Date().toISOString() });
    }
  };

  findJobs = async (req: Request, res: Response) => {
    try {
      const jobs = await this.chequeJobService.findChequeJobs(req.body);
      if (!jobs || jobs.length === 0) {
        return res.status(404).json({ success: false, message: 'Job not found', timestamp: new Date().toISOString() });
      }
      const job = jobs[0];
      return res.json({
        success: true,
        jobId: job.jobId,
        remarks: job.remarks,
        timestamp: new Date().toISOString(),
      });
    } catch (err) {
      console.error('Error in findRemarks:', err);
      return res.status(500).json({ success: false, message: 'Internal server error', timestamp: new Date().toISOString() });
    }
  };


  existJob = async (req: Request, res: Response) => {
  try {
    const jobs = await this.chequeJobService.findChequeJobs(req.body);
    if (!jobs || jobs.length === 0) {
      return res.status(404).json({ success: false, message: 'Job not found', timestamp: new Date().toISOString() });
    }
    const job = jobs[0];
    return res.status(200).json({ success: true, message: 'Job exists', timestamp: new Date().toISOString() });
  } catch (err) {
    console.error('Error in findRemarks:', err);
    return res.status(500).json({ success: false, message: 'Internal server error', timestamp: new Date().toISOString() });
  }
};
} 