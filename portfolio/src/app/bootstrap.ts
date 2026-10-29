import { GetPortfolio } from '@/modules/portfolio/application/use-cases/get-portfolio'
import { StaticPortfolioRepository } from '@/modules/portfolio/infrastructure/repositories/static-portfolio-repository'

// Composition root: concrete adapters are wired here, outside the feature's UI.
export function createDependencies() {
  return { getPortfolio: new GetPortfolio(new StaticPortfolioRepository()) }
}
