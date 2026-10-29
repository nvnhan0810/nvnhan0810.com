import type { Portfolio } from '../../domain/entities/portfolio'
import type { PortfolioRepository } from '../../domain/repositories/portfolio-repository'

export class GetPortfolio {
  constructor(private readonly repository: PortfolioRepository) {}

  execute(): Promise<Portfolio> {
    return this.repository.getPortfolio()
  }
}
