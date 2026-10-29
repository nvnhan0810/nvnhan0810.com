import type { Portfolio } from '../../domain/entities/portfolio'
import type { PortfolioRepository } from '../../domain/repositories/portfolio-repository'
import { portfolioData } from '../data/portfolio-data'

export class StaticPortfolioRepository implements PortfolioRepository {
  getPortfolio(): Promise<Portfolio> {
    return Promise.resolve(portfolioData)
  }
}
