import type { Portfolio } from '../entities/portfolio'

export interface PortfolioRepository {
  getPortfolio(): Promise<Portfolio>
}
