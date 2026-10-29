import type { InjectionKey } from 'vue'
import type { GetPortfolio } from '../application/use-cases/get-portfolio'
import type { Locale, Portfolio } from '../domain/entities/portfolio'

export const portfolioKey: InjectionKey<GetPortfolio> = Symbol('portfolio')

export interface PortfolioState {
  readonly portfolio: Portfolio
  readonly locale: Locale
  readonly year: number
}

export const portfolioStateKey: InjectionKey<PortfolioState> = Symbol('portfolio-state')
