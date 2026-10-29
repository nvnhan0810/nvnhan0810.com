import type { InjectionKey } from 'vue'
import type { GetPortfolio } from '../application/use-cases/get-portfolio'

export const portfolioKey: InjectionKey<GetPortfolio> = Symbol('portfolio')
