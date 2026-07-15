import { describe, it, expect } from 'vitest';
import { ExpenseParser } from './utils/expense-parser';

describe('ExpenseParser with Validation', () => {
  it('should parse "50k makan siang"', () => {
    const result = ExpenseParser.parse('/log 50k makan siang');
    expect(result).toEqual({ amount: 50000, description: 'makan siang', category: 'General' });
  });

  it('should parse "12.500 kopi"', () => {
    const result = ExpenseParser.parse('/catat 12.500 kopi');
    expect(result).toEqual({ amount: 12500, description: 'kopi', category: 'General' });
  });

  it('should reject ambiguous input like "50kk makan"', () => {
    const result = ExpenseParser.parse('/log 50kk makan');
    expect(result).toBeNull();
  });

  it('should reject ambiguous input like "50rb10 makan"', () => {
    const result = ExpenseParser.parse('/log 50rb10 makan');
    expect(result).toBeNull();
  });

  it('should reject zero or negative values', () => {
    expect(ExpenseParser.parse('/log 0 makan')).toBeNull();
    expect(ExpenseParser.parse('/log -50k makan')).toBeNull();
  });

  it('should reject extremely high, unrealistic inputs (safety guard)', () => {
    expect(ExpenseParser.parse('/log 150000000k jet-pri')).toBeNull(); // 150 Billion
  });
});
